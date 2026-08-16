<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SyncStatus;
use App\Models\Document;
use App\Models\Source;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => Source::factory(),
            'path' => fake()->unique()->lexify('notes/????.md'),
            'title' => fake()->sentence(3),
            'content_hash' => hash('sha256', fake()->unique()->text()),
            'chunk_count' => null,
            'sync_status' => SyncStatus::Pending,
            'last_error' => null,
            'indexed_at' => null,
        ];
    }

    public function indexed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sync_status' => SyncStatus::Indexed,
            'chunk_count' => fake()->numberBetween(1, 10),
            'indexed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sync_status' => SyncStatus::Failed,
            'last_error' => 'Indexing failed',
        ]);
    }
}
