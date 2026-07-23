<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\Ollama\Ollama;

/**
 * Minimaler Agent für den manuellen AP-1-Smoke gegen lokales Ollama.
 * Kein produktiver RAG-Agent (kommt als CompanionRag in AP-4).
 */
class SmokeAgent extends Agent
{
    protected function provider(): AIProviderInterface
    {
        /** @var array{url: string, model: string, parameters?: array<string, mixed>} $ollama */
        $ollama = config('neuron.provider.ollama');

        return new Ollama(
            url: $ollama['url'],
            model: $ollama['model'],
            parameters: $ollama['parameters'] ?? [],
            httpClient: new GuzzleHttpClient(
                timeout: (float) config('companion.timeouts.llm_seconds', 120),
            ),
        );
    }

    public function instructions(): string
    {
        return 'Reply briefly to confirm the local Ollama connection works.';
    }
}
