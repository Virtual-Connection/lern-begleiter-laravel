<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\IndexingService;
use App\Data\Dto\DocumentData;
use App\Models\Document;

/**
 * Temporary no-op IndexingService for AP-3: no chunking/embeddings/vector
 * store exists yet (that's AP-4). Lets the sync/reconciliation flow and its
 * jobs run end-to-end against the real interface without a real index
 * behind it. AP-4 rebinds IndexingService to the Chroma/FileVectorStore
 * implementation in AppServiceProvider - nothing else changes.
 */
final class NullIndexingService implements IndexingService
{
    public function index(Document $document, DocumentData $data): int
    {
        return 0;
    }

    public function delete(Document $document): void
    {
        // no-op: nothing indexed yet
    }
}
