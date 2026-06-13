<?php

use App\Http\Controllers\HubSpotWebhookController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hubspot', HubSpotWebhookController::class);

Route::middleware('auth')->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:manage orders');

    Route::post('/invoices/orders/{orderId}', [InvoiceController::class, 'createFromOrder'])
        ->middleware('permission:manage invoices');

    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:manage invoices');
});
