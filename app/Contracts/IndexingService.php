<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Dto\DocumentData;
use App\Exceptions\IndexingFailedException;
use App\Models\Document;

/**
 * Embeds a document's chunks and upserts/removes them in the vector store.
 * AP-3 binds this to a no-op NullIndexingService (no real index exists
 * yet); AP-4 replaces the binding with the Chunking/Embeddings/
 * FileVectorStore-backed implementation. Jobs depend on this interface
 * only, never on the concrete store.
 */
interface IndexingService
{
    /**
     * @throws IndexingFailedException
     */
    public function index(Document $document, DocumentData $data): int;

    /**
     * @throws IndexingFailedException
     */
    public function delete(Document $document): void;
}
