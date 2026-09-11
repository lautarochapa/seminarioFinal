<?php

return [
    'request_delay_ms' => (int) env('SCRAPING_REQUEST_DELAY_MS', 1500),
    'recipe_request_delay_ms' => (int) env('RECIPE_SCRAPING_REQUEST_DELAY_MS', 2500),
    'recipe_search_term' => env('RECIPE_SCRAPING_SEARCH_TERM', 'comida'),
    'connect_timeout_seconds' => (int) env('SCRAPING_CONNECT_TIMEOUT_SECONDS', 5),
    'timeout_seconds' => (int) env('SCRAPING_TIMEOUT_SECONDS', 20),
    'max_retries' => (int) env('SCRAPING_MAX_RETRIES', 2),
    'retry_backoff_seconds' => array_map('intval', explode(',', env('SCRAPING_RETRY_BACKOFF_SECONDS', '10,30'))),
    'default_retry_after_seconds' => (int) env('SCRAPING_DEFAULT_RETRY_AFTER_SECONDS', 60),
    'lock_ttl_seconds' => (int) env('SCRAPING_LOCK_TTL_SECONDS', 3600),
    'circuit_breaker' => [
        'max_errors' => (int) env('SCRAPING_CIRCUIT_BREAKER_MAX_ERRORS', 5),
        'cooldown_seconds' => (int) env('SCRAPING_CIRCUIT_BREAKER_COOLDOWN_SECONDS', 900),
    ],
    'limits' => [
        'supermarket_max_pages' => (int) env('SCRAPING_MAX_PAGES', 30),
        'supermarket_max_items' => (int) env('SCRAPING_MAX_ITEMS_PER_RUN', 3000),
        'recipe_max_pages' => (int) env('RECIPE_SCRAPING_MAX_PAGES', 10),
        'recipe_max_items' => (int) env('RECIPE_SCRAPING_MAX_RECIPES_PER_RUN', 200),
    ],
    // Corta la corrida de recetas antes de que el proceso (corre en linea bajo
    // QUEUE_CONNECTION=sync) pueda ser matado externamente por un timeout del
    // servidor web/php-fpm, dejando el job sin poder cerrar su propio estado.
    'recipe_time_budget_seconds' => (int) env('RECIPE_SCRAPING_TIME_BUDGET_SECONDS', 180),
    // Ventana tras la cual un job de recetas en pending/running sin actualizar
    // se considera huerfano (proceso caido/matado) y se reconcilia a failed.
    'recipe_stale_job_seconds' => (int) env('RECIPE_SCRAPING_STALE_JOB_SECONDS', 600),
    'user_agent' => env('SCRAPING_USER_AGENT', 'ComidaControlBot/1.0'),
];
