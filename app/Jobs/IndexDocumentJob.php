<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\IndexingService;
use App\Data\Dto\DocumentData;
use App\Enums\SyncStatus;
use App\Jobs\Concerns\HasDocumentSyncRetryPolicy;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Indexes one document: embed + upsert its chunks via IndexingService.
 * The document's content travels with the job (from the DocumentSource
 * read at sync time) rather than being re-read from disk, since
 * DocumentSource only exposes all() (Spec M1 "keine Junior-Erfindung").
 */
final class IndexDocumentJob implements ShouldQueue
{
    use Dispatchable, HasDocumentSyncRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $documentId,
        public readonly DocumentData $data,
    ) {}

    public function handle(IndexingService $indexingService): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            // Reconciliation already removed it (e.g. source deactivated
            // between dispatch and execution) - nothing left to index.
            return;
        }

        // Compare-and-swap, checked before *and* after doing the work
        // (AP-3 review): a later sync may have redispatched this same
        // document with newer content while this (possibly retried) job
        // was in flight. If the row's content_hash has already moved on -
        // either before we start or while index() was running - a fresher
        // job already owns the result; don't let a stale attempt touch the
        // status (not even the intermediate `indexing` stamp) or overwrite
        // it with outdated data.
        if ($document->content_hash !== $this->data->contentHash) {
            return;
        }

        $document->update(['sync_status' => SyncStatus::Indexing]);

        $chunkCount = $indexingService->index($document, $this->data);

        $document->refresh();

        if ($document->content_hash !== $this->data->contentHash) {
            return;
        }

        $document->update([
            'sync_status' => SyncStatus::Indexed,
            'chunk_count' => $chunkCount,
            'indexed_at' => now(),
            'last_error' => null,
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->markDocumentFailed($this->documentId, $exception, 'Indexing failed.');
    }
}
