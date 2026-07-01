<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Prefijo del número de factura
    |--------------------------------------------------------------------------
    |
    | Formato resultante: {prefix}{año}-{secuencia}
    | Ejemplo: HORECA2026-00058
    |
    */

    'number_prefix' => env('INVOICE_NUMBER_PREFIX', 'HORECA'),

    'number_padding' => (int) env('INVOICE_NUMBER_PADDING', 5),

    'default_vat_rate' => (float) env('INVOICE_DEFAULT_VAT_RATE', 0.21),

    'payment_terms_days' => (int) env('INVOICE_PAYMENT_TERMS_DAYS', 21),

    'logo_path' => env('INVOICE_LOGO_PATH', 'images/brand/atlantica-terranova-logo.png'),

    'issuer' => [
        'name' => env('INVOICE_ISSUER_NAME', 'ATLANTICA TERRANOVA 1908 S.L.'),
        'address' => env('INVOICE_ISSUER_ADDRESS', 'Calle Colina Blanca 1 Bl 2 1B'),
        'tax_id' => env('INVOICE_ISSUER_TAX_ID', 'B22978712'),
        'postal_code' => env('INVOICE_ISSUER_POSTAL_CODE', '29640'),
        'city' => env('INVOICE_ISSUER_CITY', 'Fuengirola'),
        'email' => env('INVOICE_ISSUER_EMAIL', 'ljgambande@gmail.com'),
        'iban' => env('INVOICE_ISSUER_IBAN', 'ES0900497245242510116391'),
    ],

];
