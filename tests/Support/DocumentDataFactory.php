<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Data\Dto\DocumentData;

final class DocumentDataFactory
{
    public static function make(string $path, string $content): DocumentData
    {
        return new DocumentData(
            path: $path,
            title: $path,
            content: $content,
            contentHash: hash('sha256', $content),
        );
    }
}
