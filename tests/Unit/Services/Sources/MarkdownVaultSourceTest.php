<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Models\Source;
use App\Services\Sources\MarkdownVaultSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->vaultRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'companion-vault-'.uniqid('', true);
    File::makeDirectory($this->vaultRoot, 0755, true);
});

afterEach(function (): void {
    if (is_dir($this->vaultRoot)) {
        File::deleteDirectory($this->vaultRoot);
    }
});

$makeVaultSource = fn (string $root): MarkdownVaultSource => new MarkdownVaultSource(Source::factory()->make([
    'type' => SourceType::MarkdownVault,
    'path' => $root,
]));

it('uses the first top-level heading as the title', function () use ($makeVaultSource): void {
    File::put($this->vaultRoot.DIRECTORY_SEPARATOR.'note.md', "# Real Title\n\nBody text.");

    $documents = iterator_to_array($makeVaultSource($this->vaultRoot)->all());

    expect($documents)->toHaveCount(1);
    expect($documents[0]->title)->toBe('Real Title');
});

it('ignores a "# ..." line inside a fenced code block when extracting the title', function () use ($makeVaultSource): void {
    $content = <<<'MD'
        ```bash
        # this is a bash comment, not a heading
        echo hi
        ```

        # Real Title

        Body text.
        MD;
    File::put($this->vaultRoot.DIRECTORY_SEPARATOR.'note.md', $content);

    $documents = iterator_to_array($makeVaultSource($this->vaultRoot)->all());

    expect($documents)->toHaveCount(1);
    expect($documents[0]->title)->toBe('Real Title');
});

it('falls back to the filename when no heading exists outside of code fences', function () use ($makeVaultSource): void {
    $content = <<<'MD'
        ```bash
        # only a comment in here
        ```
        MD;
    File::put($this->vaultRoot.DIRECTORY_SEPARATOR.'no-heading.md', $content);

    $documents = iterator_to_array($makeVaultSource($this->vaultRoot)->all());

    expect($documents)->toHaveCount(1);
    expect($documents[0]->title)->toBe('no-heading');
});
