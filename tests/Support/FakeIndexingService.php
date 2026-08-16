<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\IndexingService;
use App\Data\Dto\DocumentData;
use App\Exceptions\IndexingFailedException;
use App\Models\Document;

final class FakeIndexingService implements IndexingService
{
    public int $indexCalls = 0;

    public int $deleteCalls = 0;

    public function __construct(private readonly bool $shouldFail = false) {}

    public function index(Document $document, DocumentData $data): int
    {
        $this->indexCalls++;

        if ($this->shouldFail) {
            throw new IndexingFailedException('Fake indexing failure.');
        }

        return 3;
    }

    public function delete(Document $document): void
    {
        $this->deleteCalls++;

        if ($this->shouldFail) {
            throw new IndexingFailedException('Fake delete failure.');
        }
    }
}
