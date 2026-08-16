<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\DocumentSourceFactory;
use App\Enums\SyncStatus;
use App\Jobs\DeleteDocumentJob;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Diffs every active Source's on-disk documents against the known
 * `documents` rows and dispatches index/delete jobs for what changed.
 * Builds the three sets from Spec M1 (neu, geändert, verschwunden) plus a
 * fourth: previously failed documents are retried even without a content
 * change, so a failed index attempt does not silently stall forever
 * (Spec M1 Fehlerbehandlung: "Dokument bleibt beim nächsten Sync Kandidat").
 * A fifth case (AP-3 review) covers documents stuck at `pending`/`indexing`
 * because their job never reached failed() (worker killed, no worker
 * running, `queue:restart` racing an in-flight job) - those are reclaimed
 * as retry candidates once they're older than STALE_AFTER_MINUTES.
 */
final class CorpusSyncService
{
    /**
     * How long a document may sit at `pending`/`indexing` before the next
     * sync treats it as stuck and retries it - comfortably above the jobs'
     * own tries=3/backoff=[10,30,60]s (~100s) window, per AP-3 review.
     */
    private const int STALE_AFTER_MINUTES = 10;

    /**
     * Guards against two corpus:sync runs (e.g. an overlapping cron + manual
     * invocation) racing on the same `documents.(source_id, path)` unique
     * constraint (AP-3 review): a run that can't acquire the lock skips
     * outright instead of dispatching duplicate/conflicting work.
     */
    private const string LOCK_KEY = 'corpus-sync';

    private const int LOCK_SECONDS = 300;

    public function __construct(
        private readonly DocumentSourceFactory $sourceFactory,
    ) {}

    /**
     * @return array{new: int, changed: int, retried: int, unchanged: int, removed: int, failed_sources: list<string>, locked: bool}
     */
    public function sync(): array
    {
        $totals = [
            'new' => 0,
            'changed' => 0,
            'retried' => 0,
            'unchanged' => 0,
            'removed' => 0,
            'failed_sources' => [],
            'locked' => false,
        ];

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            $totals['locked'] = true;

            return $totals;
        }

        try {
            /** @var Source $source */
            foreach (Source::query()->where('enabled', true)->get() as $source) {
                try {
                    $totals = $this->syncSource($source, $totals);
                } catch (Throwable $exception) {
                    // One source's failure (bad SourceType, unreadable
                    // directory, DB error, ...) must not abort the sync for
                    // every other source (AP-3 review).
                    Log::error('CorpusSyncService: source sync failed', [
                        'source_id' => $source->id,
                        'source' => $source->name,
                        'error' => $exception->getMessage(),
                    ]);
                    $totals['failed_sources'][] = $source->name;
                }
            }
        } finally {
            $lock->release();
        }

        return $totals;
    }

    /**
     * @param  array{new: int, changed: int, retried: int, unchanged: int, removed: int, failed_sources: list<string>, locked: bool}  $totals
     * @return array{new: int, changed: int, retried: int, unchanged: int, removed: int, failed_sources: list<string>, locked: bool}
     */
    private function syncSource(Source $source, array $totals): array
    {
        $documentSource = $this->sourceFactory->make($source);

        /** @var Collection<string, Document> $existingByPath */
        $existingByPath = $source->documents()->get()->keyBy('path');
        $seenPaths = [];
        $staleBefore = Carbon::now()->subMinutes(self::STALE_AFTER_MINUTES);

        foreach ($documentSource->all() as $data) {
            $seenPaths[$data->path] = true;
            /** @var Document|null $existing */
            $existing = $existingByPath->get($data->path);

            if ($existing === null) {
                $document = $source->documents()->create([
                    'path' => $data->path,
                    'title' => $data->title,
                    'content_hash' => $data->contentHash,
                    'sync_status' => SyncStatus::Pending,
                ]);
                IndexDocumentJob::dispatch($document->id, $data);
                $totals['new']++;

                continue;
            }

            $contentChanged = $existing->content_hash !== $data->contentHash;
            $isStuck = in_array($existing->sync_status, [SyncStatus::Pending, SyncStatus::Indexing], true)
                && $existing->updated_at !== null
                && $existing->updated_at->lessThan($staleBefore);
            $isRetry = ! $contentChanged && ($existing->sync_status === SyncStatus::Failed || $isStuck);

            if (! $contentChanged && ! $isRetry) {
                $totals['unchanged']++;

                continue;
            }

            $existing->update([
                'title' => $data->title,
                'content_hash' => $data->contentHash,
                'sync_status' => SyncStatus::Pending,
            ]);
            IndexDocumentJob::dispatch($existing->id, $data);
            $totals[$isRetry ? 'retried' : 'changed']++;
        }

        foreach ($existingByPath as $path => $document) {
            if (! isset($seenPaths[$path])) {
                DeleteDocumentJob::dispatch($document->id);
                $totals['removed']++;
            }
        }

        return $totals;
    }
}
