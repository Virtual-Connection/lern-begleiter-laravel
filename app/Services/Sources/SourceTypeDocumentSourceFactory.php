<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Contracts\DocumentSource;
use App\Contracts\DocumentSourceFactory;
use App\Enums\SourceType;
use App\Models\Source;

/**
 * Maps Source::$type to its DocumentSource implementation. M1 only knows
 * markdown_vault; M4 adds docx_folder as another SourceType case + match arm.
 */
final class SourceTypeDocumentSourceFactory implements DocumentSourceFactory
{
    public function make(Source $source): DocumentSource
    {
        return match ($source->type) {
            SourceType::MarkdownVault => new MarkdownVaultSource($source),
        };
    }
}
