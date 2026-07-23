<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\Agent\Agent;
use NeuronAI\HttpClient\GuzzleHttpClient;
use NeuronAI\Laravel\Facades\AIProvider;
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
        $provider = AIProvider::driver();

        // CPU-Smoke braucht längeren Timeout als Neuron-Default (60s)
        if ($provider instanceof Ollama) {
            $provider->setHttpClient(
                (new GuzzleHttpClient(
                    timeout: (float) config('companion.timeouts.llm_seconds', 120),
                ))->withBaseUri((string) config('neuron.provider.ollama.url')),
            );
        }

        return $provider;
    }

    public function instructions(): string
    {
        return 'Reply briefly to confirm the local Ollama connection works.';
    }
}
