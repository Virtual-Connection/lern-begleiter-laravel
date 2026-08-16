<?php

declare(strict_types=1);

namespace App\Jobs\Concerns;

use App\Enums\SyncStatus;
use App\Models\Document;
use Throwable;

/**
 * Shared retry policy and failure bookkeeping for the document sync jobs
 * (IndexDocumentJob, DeleteDocumentJob). Kept in one place so tuning the
 * backoff schedule or the "mark as failed" write can't drift between the
 * two jobs (AP-3 review).
 */
trait HasDocumentSyncRetryPolicy
{
    /** @var int */
    public $tries = 3;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    private function markDocumentFailed(string $documentId, ?Throwable $exception, string $defaultMessage): void
    {
        Document::query()->whereKey($documentId)->update([
            'sync_status' => SyncStatus::Failed,
            'last_error' => $exception?->getMessage() ?? $defaultMessage,
        ]);
    }
}
