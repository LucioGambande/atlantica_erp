<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\HubSpotWebhookController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StockController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/hubspot', HubSpotWebhookController::class);

Route::middleware('auth')->group(function () {
    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware('permission:manage orders');

    Route::post('/invoices/orders/{orderId}', [InvoiceController::class, 'createFromOrder'])
        ->middleware('permission:manage invoices');

    Route::post('/payments', [PaymentController::class, 'store'])
        ->middleware('permission:manage invoices');

    Route::middleware('permission:manage stock')->group(function () {
        Route::get('/stock/products', [StockController::class, 'products']);
        Route::get('/stock/summary', [StockController::class, 'summary']);
    });

    Route::middleware('permission:manage invoices')->group(function () {
        Route::get('/billing/invoices', [BillingController::class, 'invoices']);
        Route::get('/billing/summary', [BillingController::class, 'summary']);
        Route::get('/billing/trend', [BillingController::class, 'trend']);
    });
});
