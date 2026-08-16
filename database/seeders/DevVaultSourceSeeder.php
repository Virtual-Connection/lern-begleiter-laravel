<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\SourceType;
use App\Models\Source;
use Illuminate\Database\Seeder;

class DevVaultSourceSeeder extends Seeder
{
    /**
     * Seed the two M1 vault folders as sources (if they exist on disk).
     */
    public function run(): void
    {
        $vaultRoot = config('companion.vault_root');

        if (! is_string($vaultRoot) || $vaultRoot === '') {
            $this->command?->warn('COMPANION_VAULT_ROOT ist nicht gesetzt – Dev-Seed übersprungen.');

            return;
        }

        $resolvedRoot = realpath($vaultRoot);

        if ($resolvedRoot === false || ! is_dir($resolvedRoot)) {
            $this->command?->warn("Vault-Root existiert nicht: {$vaultRoot}");

            return;
        }

        $folders = [
            'PHP und Laravel' => 'PHP und Laravel',
            'AI Development' => 'AI Development',
        ];

        foreach ($folders as $name => $relative) {
            $absolute = $resolvedRoot.DIRECTORY_SEPARATOR.$relative;
            $resolved = realpath($absolute);

            if ($resolved === false || ! is_dir($resolved) || ! is_readable($resolved)) {
                $this->command?->warn("Ordner fehlt oder nicht lesbar: {$absolute}");

                continue;
            }

            Source::query()->updateOrCreate(
                ['path' => $resolved],
                [
                    'name' => $name,
                    'type' => SourceType::MarkdownVault,
                    'enabled' => true,
                ],
            );

            $this->command?->info("Quelle gesichert: {$name} → {$resolved}");
        }
    }
}
