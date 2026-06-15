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

];
