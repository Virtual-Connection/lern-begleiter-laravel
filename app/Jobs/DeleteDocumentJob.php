<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\IndexingService;
use App\Jobs\Concerns\HasDocumentSyncRetryPolicy;
use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Removes a document that disappeared from its source: purge its chunks
 * from the index first, only then hard-delete the `documents` row (Spec M1
 * Lösch-Semantik - order matters, a crash between the two steps must leave
 * something to retry, not an orphaned index entry).
 */
final class DeleteDocumentJob implements ShouldQueue
{
    use Dispatchable, HasDocumentSyncRetryPolicy, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $documentId,
    ) {}

    public function handle(IndexingService $indexingService): void
    {
        $document = Document::query()->find($this->documentId);

        if ($document === null) {
            // Already gone - e.g. a duplicate delete dispatch from a sync
            // run that overlapped with a previous one. Idempotent no-op.
            return;
        }

        $indexingService->delete($document);

        $document->delete();
    }

    public function failed(?Throwable $exception): void
    {
        // Index deletion failed: the row stays (as `failed`) so the next
        // corpus:sync run still sees it as "disappeared" and retries the
        // delete, instead of leaving an orphaned index entry unnoticed.
        $this->markDocumentFailed($this->documentId, $exception, 'Index deletion failed.');
    }
}
