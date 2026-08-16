<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\DocumentSource;
use App\Contracts\DocumentSourceFactory;
use App\Models\Source;
use RuntimeException;

/**
 * Wraps another DocumentSourceFactory but throws for one specific Source id -
 * used to simulate a single broken source (unreadable vault, unmapped
 * SourceType, ...) while the rest of a CorpusSyncService::sync() run is
 * exercised normally.
 */
final class FakeThrowingDocumentSourceFactory implements DocumentSourceFactory
{
    public function __construct(
        private readonly DocumentSourceFactory $inner,
        private readonly string $brokenSourceId,
        private readonly string $message = 'Simulated source failure.',
    ) {}

    public function make(Source $source): DocumentSource
    {
        if ($source->id === $this->brokenSourceId) {
            throw new RuntimeException($this->message);
        }

        return $this->inner->make($source);
    }
}
