<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Wraps failures from the indexing dependency (embeddings/vector store)
 * behind a stable app-level exception, per Spec M1 "Fehlerbehandlung".
 */
final class IndexingFailedException extends RuntimeException {}
