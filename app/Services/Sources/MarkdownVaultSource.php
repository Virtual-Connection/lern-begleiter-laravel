<?php

declare(strict_types=1);

namespace App\Services\Sources;

use App\Contracts\DocumentSource;
use App\Data\Dto\DocumentData;
use App\Models\Source;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * Reads a Source of type markdown_vault: every *.md file under the
 * source's root folder becomes one DocumentData.
 */
final readonly class MarkdownVaultSource implements DocumentSource
{
    public function __construct(private Source $source) {}

    /**
     * @return iterable<DocumentData>
     */
    public function all(): iterable
    {
        $root = $this->source->path;

        if (! is_dir($root)) {
            return;
        }

        $finder = (new Finder)
            ->files()
            ->in($root)
            ->name('*.md')
            ->sortByName();

        foreach ($finder as $file) {
            try {
                $content = $file->getContents();
            } catch (Throwable $exception) {
                // Unreadable single file must not abort the whole source scan.
                Log::warning('MarkdownVaultSource: could not read file', [
                    'source_id' => $this->source->id,
                    'file' => $file->getPathname(),
                    'error' => $exception->getMessage(),
                ]);

                continue;
            }

            // Relative path with forward slashes, stable across OSes -
            // this is what's persisted as documents.path and diffed on.
            $relativePath = str_replace('\\', '/', $file->getRelativePathname());

            yield new DocumentData(
                path: $relativePath,
                title: $this->extractTitle($content) ?? $file->getFilenameWithoutExtension(),
                content: $content,
                contentHash: hash('sha256', $content),
            );
        }
    }

    /**
     * First top-level `# heading` line, ignoring anything inside a fenced
     * code block (``` or ~~~) - a `# ` line in a shell/YAML/Python example
     * must not be mistaken for the document's title (AP-3 review).
     */
    private function extractTitle(string $content): ?string
    {
        $inFence = false;

        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            if (preg_match('/^(```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;

                continue;
            }

            if (! $inFence && preg_match('/^#\s+(.+)$/', $line, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        return null;
    }
}
