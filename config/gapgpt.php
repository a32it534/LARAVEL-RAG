<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GapGPT API Configuration
    |--------------------------------------------------------------------------
    */
    'api_key' => env('GAPGPT_API_KEY', ''),
    'base_url' => env('GAPGPT_BASE_URL', 'https://api.gapgpt.app/v1'),
    'chat_model' => env('GAPGPT_CHAT_MODEL', 'gpt-4o'),
    'embedding_model' => env('GAPGPT_EMBEDDING_MODEL', 'text-embedding-3-large'),
    'top_k' => (int) env('RAG_TOP_K', 4),
    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 600),
    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 80),
];
