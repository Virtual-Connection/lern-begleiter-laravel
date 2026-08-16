<?php

declare(strict_types=1);

use App\Enums\SyncStatus;
use App\Exceptions\IndexingFailedException;
use App\Jobs\DeleteDocumentJob;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeIndexingService;

uses(RefreshDatabase::class);

it('purges the index before hard-deleting the row', function (): void {
    $document = Document::factory()->for(Source::factory())->indexed()->create();
    $indexingService = new FakeIndexingService;

    (new DeleteDocumentJob($document->id))->handle($indexingService);

    expect($indexingService->deleteCalls)->toBe(1);
    expect(Document::query()->find($document->id))->toBeNull();
});

it('does nothing when the document is already gone (duplicate dispatch)', function (): void {
    $indexingService = new FakeIndexingService;

    (new DeleteDocumentJob('01ARZ3NDEKTSV4RRFFQ69G5FAV'))->handle($indexingService);

    expect($indexingService->deleteCalls)->toBe(0);
});

it('keeps the row as failed when index deletion fails, instead of deleting it', function (): void {
    $document = Document::factory()->for(Source::factory())->indexed()->create();
    $indexingService = new FakeIndexingService(shouldFail: true);

    expect(fn () => (new DeleteDocumentJob($document->id))->handle($indexingService))
        ->toThrow(IndexingFailedException::class);

    expect(Document::query()->find($document->id))->not->toBeNull();

    (new DeleteDocumentJob($document->id))->failed(new RuntimeException('Chroma down'));

    $document->refresh();
    expect($document->sync_status)->toBe(SyncStatus::Failed);
    expect($document->last_error)->toBe('Chroma down');
});
