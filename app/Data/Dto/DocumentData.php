<?php

declare(strict_types=1);

namespace App\Data\Dto;

/**
 * One document as read from a DocumentSource, before it has (or independent
 * of whether it already has) a persisted `documents` row.
 */
final readonly class DocumentData
{
    public function __construct(
        public string $path,
        public string $title,
        public string $content,
        public string $contentHash,
    ) {}
}
