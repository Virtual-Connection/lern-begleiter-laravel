<?php

declare(strict_types=1);

use App\Contracts\IndexingService;
use App\Data\Dto\DocumentData;
use App\Enums\SyncStatus;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DocumentDataFactory;
use Tests\Support\FakeIndexingService;

uses(RefreshDatabase::class);

it('indexes the document and marks it indexed on success', function (): void {
    $data = DocumentDataFactory::make('a.md', 'Content A');
    $document = Document::factory()->for(Source::factory())->create([
        'path' => 'a.md',
        'content_hash' => $data->contentHash,
        'sync_status' => SyncStatus::Pending,
    ]);
    $indexingService = new FakeIndexingService;

    (new IndexDocumentJob($document->id, $data))->handle($indexingService);

    expect($indexingService->indexCalls)->toBe(1);
    $document->refresh();
    expect($document->sync_status)->toBe(SyncStatus::Indexed);
    expect($document->chunk_count)->toBe(3);
    expect($document->indexed_at)->not->toBeNull();
    expect($document->last_error)->toBeNull();
});

it('does not overwrite a fresher result when the row moved on to newer content while this job was in flight', function (): void {
    $staleData = DocumentDataFactory::make('a.md', 'Stale content (H1)');
    $document = Document::factory()->for(Source::factory())->create([
        'path' => 'a.md',
        // The row already reflects a newer sync's content (H2): a second,
        // more recent IndexDocumentJob has since run/dispatched for it.
        'content_hash' => hash('sha256', 'Newer content (H2)'),
        'sync_status' => SyncStatus::Indexed,
        'chunk_count' => 7,
    ]);
    $indexingService = new FakeIndexingService;

    (new IndexDocumentJob($document->id, $staleData))->handle($indexingService);

    expect($indexingService->indexCalls)->toBe(0); // stale before it even started - skipped entirely
    $document->refresh();
    expect($document->sync_status)->toBe(SyncStatus::Indexed); // untouched, not reset to "indexing"
    expect($document->chunk_count)->toBe(7); // untouched, not overwritten with the stale job's value
});

it('does not overwrite a fresher result when the row moves on to newer content mid-flight', function (): void {
    $data = DocumentDataFactory::make('a.md', 'Content A');
    $document = Document::factory()->for(Source::factory())->create([
        'path' => 'a.md',
        'content_hash' => $data->contentHash,
        'sync_status' => SyncStatus::Pending,
    ]);

    // Simulates a second, newer sync completing its own IndexDocumentJob
    // while this job's index() call is still running.
    $indexingService = new class implements IndexingService
    {
        public function index(Document $document, DocumentData $data): int
        {
            Document::query()->whereKey($document->id)->update([
                'content_hash' => hash('sha256', 'Newer content (H2)'),
                'sync_status' => SyncStatus::Indexed,
                'chunk_count' => 9,
            ]);

            return 3;
        }

        public function delete(Document $document): void {}
    };

    (new IndexDocumentJob($document->id, $data))->handle($indexingService);

    $document->refresh();
    expect($document->sync_status)->toBe(SyncStatus::Indexed); // from the "newer" write, not clobbered
    expect($document->chunk_count)->toBe(9); // the newer job's value survives
});

it('does nothing when the document no longer exists', function (): void {
    $data = DocumentDataFactory::make('a.md', 'Content A');
    $indexingService = new FakeIndexingService;

    (new IndexDocumentJob('01ARZ3NDEKTSV4RRFFQ69G5FAV', $data))->handle($indexingService);

    expect($indexingService->indexCalls)->toBe(0);
});

it('marks the document failed with the error message when indexing fails', function (): void {
    $document = Document::factory()->for(Source::factory())->create([
        'path' => 'a.md',
        'sync_status' => SyncStatus::Pending,
    ]);
    $data = DocumentDataFactory::make('a.md', 'Content A');

    (new IndexDocumentJob($document->id, $data))->failed(new RuntimeException('Ollama down'));

    $document->refresh();
    expect($document->sync_status)->toBe(SyncStatus::Failed);
    expect($document->last_error)->toBe('Ollama down');
});
