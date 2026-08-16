<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\DocumentSourceFactory;
use App\Contracts\IndexingService;
use App\Services\NullIndexingService;
use App\Services\Sources\SourceTypeDocumentSourceFactory;
use Illuminate\Support\ServiceProvider;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Laravel\Facades\AIProvider;
use NeuronAI\Providers\OpenAILike;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DocumentSourceFactory::class, SourceTypeDocumentSourceFactory::class);

        // AP-4 rebinds this to the real Chunking/Embeddings/FileVectorStore
        // implementation; AP-3 only wires the sync/job flow against the
        // interface (see App\Services\NullIndexingService).
        $this->app->bind(IndexingService::class, NullIndexingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AIProvider::extend('openrouter', function (): OpenAILike {
            /** @var array{key: string|null, model: string, base_uri: string, parameters?: array<string, mixed>} $config */
            $config = config('neuron.provider.openrouter');

            return new OpenAILike(
                baseUri: $config['base_uri'],
                key: (string) $config['key'],
                model: $config['model'],
                parameters: $config['parameters'] ?? [],
                httpClient: new GuzzleHttpClient(
                    customHeaders: [
                        'HTTP-Referer' => (string) config('app.url'),
                        'X-Title' => (string) config('app.name'),
                    ],
                    timeout: (float) config('companion.timeouts.llm_seconds', 120),
                ),
            );
        });
    }
}
