<?php

declare(strict_types=1);

use App\Enums\SyncStatus;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows the per-status summary and each document for a source', function (): void {
    $source = Source::factory()->create(['name' => 'Vault A']);

    Document::factory()->for($source)->indexed()->create([
        'path' => 'a.md',
        'title' => 'Note A',
    ]);
    Document::factory()->for($source)->create([
        'path' => 'b.md',
        'title' => 'Note B',
        'sync_status' => SyncStatus::Pending,
    ]);
    Document::factory()->for($source)->failed()->create([
        'path' => 'c.md',
        'title' => 'Note C',
        'last_error' => 'Ollama nicht erreichbar',
    ]);

    $this->get(route('sources.show', $source))
        ->assertSuccessful()
        ->assertSee('Vault A')
        ->assertSee('3 Dokument(e)')
        ->assertSee('1 indexed')
        ->assertSee('1 pending')
        ->assertSee('1 failed')
        ->assertSee('a.md')
        ->assertSee('Note A')
        ->assertSee('b.md')
        ->assertSee('c.md')
        ->assertSee('Ollama nicht erreichbar');
});

it('shows an empty state when a source has no documents yet', function (): void {
    $source = Source::factory()->create();

    $this->get(route('sources.show', $source))
        ->assertSuccessful()
        ->assertSee('Noch keine Dokumente');
});

it('links from the sources list to the detail page', function (): void {
    $source = Source::factory()->create(['name' => 'Vault A']);

    $this->get(route('sources.index'))
        ->assertSuccessful()
        ->assertSee(route('sources.show', $source), false);
});
