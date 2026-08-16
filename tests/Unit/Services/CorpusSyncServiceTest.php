<?php

declare(strict_types=1);

use App\Enums\SyncStatus;
use App\Jobs\DeleteDocumentJob;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\Source;
use App\Services\CorpusSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\Support\DocumentDataFactory;
use Tests\Support\FakeDocumentSource;
use Tests\Support\FakeDocumentSourceFactory;
use Tests\Support\FakeThrowingDocumentSourceFactory;

uses(RefreshDatabase::class);

it('creates pending documents and dispatches an index job for new files', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'Content A'),
            DocumentDataFactory::make('b.md', 'Content B'),
        ]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 2, 'changed' => 0, 'retried' => 0, 'unchanged' => 0, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    expect(Document::query()->count())->toBe(2);
    expect(Document::query()->where('sync_status', SyncStatus::Pending)->count())->toBe(2);
    Queue::assertPushed(IndexDocumentJob::class, 2);
});

it('is idempotent: a second sync with unchanged content dispatches nothing', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $documentSource = new FakeDocumentSource([
        DocumentDataFactory::make('a.md', 'Content A'),
    ]);
    $factory = new FakeDocumentSourceFactory([$source->id => $documentSource]);
    $service = new CorpusSyncService($factory);

    $service->sync();
    // Simulate the (faked) IndexDocumentJob having run successfully.
    Document::query()->update(['sync_status' => SyncStatus::Indexed]);

    $totals = $service->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 0, 'unchanged' => 1, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    Queue::assertPushed(IndexDocumentJob::class, 1); // only from the first sync
});

it('detects changed content and redispatches an index job', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $document = Document::factory()->for($source)->indexed()->create([
        'path' => 'a.md',
        'content_hash' => hash('sha256', 'old content'),
    ]);
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'new content'),
        ]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 1, 'retried' => 0, 'unchanged' => 0, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    expect($document->fresh()->content_hash)->toBe(hash('sha256', 'new content'));
    expect($document->fresh()->sync_status)->toBe(SyncStatus::Pending);
    Queue::assertPushed(IndexDocumentJob::class, 1);
});

it('retries a previously failed document even without a content change', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $document = Document::factory()->for($source)->failed()->create([
        'path' => 'a.md',
        'content_hash' => hash('sha256', 'same content'),
    ]);
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'same content'),
        ]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 1, 'unchanged' => 0, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    expect($document->fresh()->sync_status)->toBe(SyncStatus::Pending);
    Queue::assertPushed(IndexDocumentJob::class, 1);
});

it('dispatches a delete job for a document that disappeared from its source', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $document = Document::factory()->for($source)->indexed()->create(['path' => 'gone.md']);
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 0, 'unchanged' => 0, 'removed' => 1, 'failed_sources' => [], 'locked' => false]);
    Queue::assertPushed(DeleteDocumentJob::class, fn (DeleteDocumentJob $job): bool => $job->documentId === $document->id);
});

it('only syncs active sources, leaving disabled sources untouched', function (): void {
    Queue::fake();

    $disabled = Source::factory()->disabled()->create();
    Document::factory()->for($disabled)->indexed()->create(['path' => 'still-here.md']);
    $factory = new FakeDocumentSourceFactory([
        $disabled->id => new FakeDocumentSource([]), // would look "removed" if it were synced
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 0, 'unchanged' => 0, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    Queue::assertNotPushed(DeleteDocumentJob::class);
});

it('reclaims a document stuck at pending/indexing longer than the stale threshold', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $document = Document::factory()->for($source)->create([
        'path' => 'a.md',
        'content_hash' => hash('sha256', 'same content'),
        'sync_status' => SyncStatus::Indexing,
        'updated_at' => now()->subMinutes(11),
    ]);
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'same content'),
        ]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 1, 'unchanged' => 0, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    expect($document->fresh()->sync_status)->toBe(SyncStatus::Pending);
    Queue::assertPushed(IndexDocumentJob::class, 1);
});

it('does not yet retry a document still pending/indexing within the stale threshold', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    Document::factory()->for($source)->create([
        'path' => 'a.md',
        'content_hash' => hash('sha256', 'same content'),
        'sync_status' => SyncStatus::Pending,
        'updated_at' => now()->subMinutes(1),
    ]);
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'same content'),
        ]),
    ]);

    $totals = (new CorpusSyncService($factory))->sync();

    expect($totals)->toBe(['new' => 0, 'changed' => 0, 'retried' => 0, 'unchanged' => 1, 'removed' => 0, 'failed_sources' => [], 'locked' => false]);
    Queue::assertNotPushed(IndexDocumentJob::class);
});

it('isolates a failing source: other sources still sync and the failure is reported', function (): void {
    Queue::fake();

    $broken = Source::factory()->create(['name' => 'Broken Vault']);
    $healthy = Source::factory()->create(['name' => 'Healthy Vault']);

    $factory = new FakeDocumentSourceFactory([
        $healthy->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'Content A'),
        ]),
        // No entry for $broken->id: FakeDocumentSourceFactory falls back to
        // an empty FakeDocumentSource, so simulate a broken source directly
        // via a throwing fake instead.
    ]);
    $throwingFactory = new FakeThrowingDocumentSourceFactory($factory, $broken->id, 'Vault unreachable');

    $totals = (new CorpusSyncService($throwingFactory))->sync();

    expect($totals['new'])->toBe(1);
    expect($totals['failed_sources'])->toBe(['Broken Vault']);
    expect(Document::query()->where('source_id', $healthy->id)->count())->toBe(1);
    Queue::assertPushed(IndexDocumentJob::class, 1);
});

it('skips the run when another sync already holds the lock', function (): void {
    Queue::fake();

    $source = Source::factory()->create();
    $factory = new FakeDocumentSourceFactory([
        $source->id => new FakeDocumentSource([
            DocumentDataFactory::make('a.md', 'Content A'),
        ]),
    ]);

    $lock = Cache::lock('corpus-sync', 300);
    $lock->get();

    try {
        $totals = (new CorpusSyncService($factory))->sync();

        expect($totals['locked'])->toBeTrue();
        expect(Document::query()->count())->toBe(0);
        Queue::assertNotPushed(IndexDocumentJob::class);
    } finally {
        $lock->release();
    }
});
