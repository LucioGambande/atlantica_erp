<?php

return [
    'base_url' => env('HUBSPOT_BASE_URL', 'https://api.hubapi.com'),
    'access_token' => env('HUBSPOT_ACCESS_TOKEN'),
    'client_secret' => env('HUBSPOT_CLIENT_SECRET'),
    'timeout_seconds' => (int) env('HUBSPOT_TIMEOUT_SECONDS', 15),
    'page_limit' => (int) env('HUBSPOT_PAGE_LIMIT', 100),
    'incremental_cache_key' => env('HUBSPOT_INCREMENTAL_CACHE_KEY', 'hubspot.companies.last_incremental_sync_at'),
    'skip_webhook_signature_validation' => (bool) env('HUBSPOT_SKIP_WEBHOOK_SIGNATURE', false),

    /*
    |--------------------------------------------------------------------------
    | HubSpot Company → Customer field map
    |--------------------------------------------------------------------------
    |
    | Keys are HubSpot property names. Values define the ERP column and how
    | to coerce the incoming value. Add new HubSpot properties here to sync
    | them automatically on webhook and full sync.
    |
    */
    'company_field_map' => [
        'name' => ['column' => 'name', 'type' => 'string'],
        'nombre_fiscal' => ['column' => 'fiscal_name', 'type' => 'string'],
        'razon_social' => ['column' => 'fiscal_name', 'type' => 'string'],
        'phone' => ['column' => 'phone', 'type' => 'string'],
        'domain' => ['column' => 'website', 'type' => 'string'],
        'city' => ['column' => 'city', 'type' => 'string'],
        'address' => ['column' => 'address', 'type' => 'string'],
        'address2' => ['column' => 'fiscal_address', 'type' => 'string'],
        'zip' => ['column' => 'postal_code', 'type' => 'string'],
        'country' => ['column' => 'country', 'type' => 'string'],
        'nif' => ['column' => 'tax_id', 'type' => 'string'],
        'hs_tax_id' => ['column' => 'tax_id', 'type' => 'string'],
        'hs_lastmodifieddate' => ['column' => 'hubspot_last_modified_at', 'type' => 'datetime'],
    ],

    /*
    | ERP-only fields: never overwritten by HubSpot sync.
    */
    'erp_only_fields' => [
        'customer_type',
        'credit_limit',
    ],

    'webhook' => [
        'subscription_types' => [
            'company.creation',
            'company.propertyChange',
        ],
    ],
];
