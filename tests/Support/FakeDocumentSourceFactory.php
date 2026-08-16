<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\DocumentSource;
use App\Contracts\DocumentSourceFactory;
use App\Models\Source;

final class FakeDocumentSourceFactory implements DocumentSourceFactory
{
    /**
     * @param  array<string, DocumentSource>  $bySourceId
     */
    public function __construct(private readonly array $bySourceId) {}

    public function make(Source $source): DocumentSource
    {
        return $this->bySourceId[$source->id] ?? new FakeDocumentSource([]);
    }
}
