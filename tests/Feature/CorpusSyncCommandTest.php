<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Jobs\DeleteDocumentJob;
use App\Jobs\IndexDocumentJob;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->vaultRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'companion-sync-'.uniqid('', true);
    File::makeDirectory($this->vaultRoot, 0755, true);
});

afterEach(function (): void {
    if (is_dir($this->vaultRoot)) {
        File::deleteDirectory($this->vaultRoot);
    }
});

it('syncs a real markdown_vault source end to end (no fakes for the DocumentSource)', function (): void {
    Queue::fake();

    File::put($this->vaultRoot.DIRECTORY_SEPARATOR.'note-a.md', "# Note A\n\nHello.");
    File::put($this->vaultRoot.DIRECTORY_SEPARATOR.'note-b.md', "# Note B\n\nWorld.");

    $source = Source::factory()->create([
        'type' => SourceType::MarkdownVault,
        'path' => $this->vaultRoot,
    ]);

    $this->artisan('corpus:sync')
        ->expectsOutputToContain('2 neu')
        ->assertSuccessful();

    expect(Document::query()->where('source_id', $source->id)->count())->toBe(2);
    $this->assertDatabaseHas('documents', ['path' => 'note-a.md', 'title' => 'Note A']);
    Queue::assertPushed(IndexDocumentJob::class, 2);

    // Second run, nothing changed on disk: fully idempotent.
    $this->artisan('corpus:sync')
        ->expectsOutputToContain('0 neu')
        ->assertSuccessful();
    Queue::assertPushed(IndexDocumentJob::class, 2); // still just the first run's 2

    // Remove one file from disk, sync again: reconciliation deletes it.
    File::delete($this->vaultRoot.DIRECTORY_SEPARATOR.'note-b.md');

    $this->artisan('corpus:sync')
        ->expectsOutputToContain('1 entfernt')
        ->assertSuccessful();
    Queue::assertPushed(DeleteDocumentJob::class, 1);
});
