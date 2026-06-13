<?php

return [
    'base_url' => env('HUBSPOT_BASE_URL', 'https://api.hubapi.com'),
    'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    'timeout_seconds' => (int) env('HUBSPOT_TIMEOUT_SECONDS', 15),
    'page_limit' => (int) env('HUBSPOT_PAGE_LIMIT', 100),
    'incremental_cache_key' => env('HUBSPOT_INCREMENTAL_CACHE_KEY', 'hubspot.companies.last_incremental_sync_at'),
];
