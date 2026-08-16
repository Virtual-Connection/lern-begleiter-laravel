<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Source;

/**
 * Resolves the concrete DocumentSource for a Source row (keyed off
 * Source::$type). Not part of the M1 spec's pinned building blocks, but
 * required so CorpusSyncService can depend on the DocumentSource interface
 * only - never on a concrete class like MarkdownVaultSource - and so tests
 * can substitute a fake without touching the filesystem.
 */
interface DocumentSourceFactory
{
    public function make(Source $source): DocumentSource;
}
