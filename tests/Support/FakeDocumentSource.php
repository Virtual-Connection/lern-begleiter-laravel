<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\DocumentSource;
use App\Data\Dto\DocumentData;

final class FakeDocumentSource implements DocumentSource
{
    /**
     * @param  list<DocumentData>  $documents
     */
    public function __construct(private readonly array $documents) {}

    /**
     * @return iterable<DocumentData>
     */
    public function all(): iterable
    {
        return $this->documents;
    }
}
