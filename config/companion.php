<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Companion defaults (M1)
    |--------------------------------------------------------------------------
    |
    | Quellen liegen in der DB (sources), nicht hier. Diese Datei hält nur
    | Betriebsparameter für Index/Retrieval/LLM.
    |
    */

    'chat_model' => env('OLLAMA_MODEL', 'qwen3:4b'),

    'embedding_model' => env('OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),

    'chroma' => [
        'collection' => env('CHROMA_COLLECTION', 'companion'),
        'host' => env('CHROMA_HOST', 'http://chroma:8000'),
    ],

    'retrieval' => [
        'top_k' => (int) env('COMPANION_TOP_K', 5),
        'score_threshold' => (float) env('COMPANION_SCORE_THRESHOLD', 0.2),
    ],

    'timeouts' => [
        'embedding_seconds' => (int) env('COMPANION_TIMEOUT_EMBEDDING', 120),
        'index_seconds' => (int) env('COMPANION_TIMEOUT_INDEX', 120),
        'search_seconds' => (int) env('COMPANION_TIMEOUT_SEARCH', 15),
        'llm_seconds' => (int) env('COMPANION_TIMEOUT_LLM', 180),
    ],
];
