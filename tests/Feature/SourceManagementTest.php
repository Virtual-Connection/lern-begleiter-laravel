<?php

declare(strict_types=1);

use App\Enums\SourceType;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->tempRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'companion-sources-'.uniqid('', true);
    File::makeDirectory($this->tempRoot, 0755, true);
});

afterEach(function (): void {
    if (isset($this->tempRoot) && is_dir($this->tempRoot)) {
        File::deleteDirectory($this->tempRoot);
    }
});

it('lists sources', function (): void {
    $dir = $this->tempRoot.DIRECTORY_SEPARATOR.'vault-a';
    File::makeDirectory($dir);

    Source::factory()->create([
        'name' => 'Vault A',
        'path' => realpath($dir),
    ]);

    $this->get(route('sources.index'))
        ->assertSuccessful()
        ->assertSee('Vault A')
        ->assertSee('aktiv');
});

it('creates a source with a valid absolute path', function (): void {
    $dir = $this->tempRoot.DIRECTORY_SEPARATOR.'valid-vault';
    File::makeDirectory($dir);
    $resolved = realpath($dir);
    expect($resolved)->not->toBeFalse();

    $this->post(route('sources.store'), [
        'name' => 'Valid Vault',
        'type' => SourceType::MarkdownVault->value,
        'path' => $resolved,
    ])
        ->assertRedirect(route('sources.index'))
        ->assertSessionHas('status');

    $this->assertDatabaseHas('sources', [
        'name' => 'Valid Vault',
        'path' => $resolved,
        'type' => SourceType::MarkdownVault->value,
        'enabled' => 1,
    ]);
});

it('rejects a non-existent path', function (): void {
    $missing = $this->tempRoot.DIRECTORY_SEPARATOR.'does-not-exist';

    $this->from(route('sources.create'))
        ->post(route('sources.store'), [
            'name' => 'Missing',
            'type' => SourceType::MarkdownVault->value,
            'path' => $missing,
        ])
        ->assertRedirect(route('sources.create'))
        ->assertSessionHasErrors('path');

    expect(Source::query()->count())->toBe(0);
});

it('rejects a duplicate path', function (): void {
    $dir = $this->tempRoot.DIRECTORY_SEPARATOR.'dup-vault';
    File::makeDirectory($dir);
    $resolved = realpath($dir);

    Source::factory()->create([
        'path' => $resolved,
    ]);

    $this->from(route('sources.create'))
        ->post(route('sources.store'), [
            'name' => 'Duplicate',
            'type' => SourceType::MarkdownVault->value,
            'path' => $resolved,
        ])
        ->assertRedirect(route('sources.create'))
        ->assertSessionHasErrors('path');

    expect(Source::query()->count())->toBe(1);
});

it('rejects path traversal segments', function (): void {
    $child = $this->tempRoot.DIRECTORY_SEPARATOR.'child';
    File::makeDirectory($child);

    $traversal = $this->tempRoot.DIRECTORY_SEPARATOR.'child'.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'child';

    $this->from(route('sources.create'))
        ->post(route('sources.store'), [
            'name' => 'Traversal',
            'type' => SourceType::MarkdownVault->value,
            'path' => $traversal,
        ])
        ->assertRedirect(route('sources.create'))
        ->assertSessionHasErrors('path');

    expect(Source::query()->count())->toBe(0);
});

it('rejects a relative path', function (): void {
    $this->from(route('sources.create'))
        ->post(route('sources.store'), [
            'name' => 'Relative',
            'type' => SourceType::MarkdownVault->value,
            'path' => 'relative'.DIRECTORY_SEPARATOR.'folder',
        ])
        ->assertRedirect(route('sources.create'))
        ->assertSessionHasErrors('path');
});

it('toggles source enabled state', function (): void {
    $dir = $this->tempRoot.DIRECTORY_SEPARATOR.'toggle-vault';
    File::makeDirectory($dir);

    $source = Source::factory()->create([
        'path' => realpath($dir),
        'enabled' => true,
    ]);

    $this->patch(route('sources.toggle', $source))
        ->assertRedirect(route('sources.index'));

    expect($source->fresh()->enabled)->toBeFalse();

    $this->patch(route('sources.toggle', $source))
        ->assertRedirect(route('sources.index'));

    expect($source->fresh()->enabled)->toBeTrue();
});

it('deletes a source and cascades documents', function (): void {
    $dir = $this->tempRoot.DIRECTORY_SEPARATOR.'delete-vault';
    File::makeDirectory($dir);

    $source = Source::factory()->create([
        'path' => realpath($dir),
    ]);

    $document = Document::factory()->create([
        'source_id' => $source->id,
    ]);

    $this->delete(route('sources.destroy', $source))
        ->assertRedirect(route('sources.index'));

    $this->assertDatabaseMissing('sources', ['id' => $source->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});
