<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Data\Dto\DocumentData;

/**
 * Reads all documents currently present in one Source (e.g. a markdown
 * folder). Implementations must not touch the database - they only know
 * how to read raw documents from wherever the Source points to.
 */
interface DocumentSource
{
    /**
     * @return iterable<DocumentData>
     */
    public function all(): iterable;
}
