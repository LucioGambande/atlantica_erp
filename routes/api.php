<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\InvoiceController as InvoiceApiController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\PurchaseInvoiceController;
use App\Http\Controllers\Api\V1\StockMovementController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\WarehouseController;
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

/*
 * API versionada para integraciones externas (y para que el propio asistente
 * pueda operar sin pasar por el navegador). Autenticación por token
 * (Sanctum), separada de las rutas de arriba (que siguen con sesión/guard
 * web): no se tocan para no romper lo que ya las consume hoy.
 */
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::middleware('permission:manage customers')->group(function () {
        Route::get('/customers', [CustomerController::class, 'index']);
        Route::get('/customers/{customer}', [CustomerController::class, 'show']);
        Route::post('/customers', [CustomerController::class, 'store']);
        Route::put('/customers/{customer}', [CustomerController::class, 'update']);
    });

    Route::middleware('permission:manage products')->group(function () {
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{product}', [ProductController::class, 'update']);
    });

    Route::middleware('permission:manage purchases')->group(function () {
        Route::get('/suppliers', [SupplierController::class, 'index']);
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show']);
        Route::post('/suppliers', [SupplierController::class, 'store']);
        Route::get('/purchase-invoices', [PurchaseInvoiceController::class, 'index']);
        Route::get('/purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'show']);
        Route::post('/purchase-invoices', [PurchaseInvoiceController::class, 'store']);
        Route::patch('/purchase-invoices/{purchaseInvoice}', [PurchaseInvoiceController::class, 'update']);
    });

    Route::middleware('permission:manage invoices')->group(function () {
        Route::get('/invoices', [InvoiceApiController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceApiController::class, 'show']);
        Route::post('/invoices/orders/{orderId}', [InvoiceController::class, 'createFromOrder']);
    });

    Route::middleware('permission:manage stock')->group(function () {
        Route::get('/warehouses', [WarehouseController::class, 'index']);
        Route::get('/warehouses/{warehouse}', [WarehouseController::class, 'show']);
        Route::post('/warehouses', [WarehouseController::class, 'store']);
        Route::put('/warehouses/{warehouse}', [WarehouseController::class, 'update']);

        Route::get('/stock-transfers', [StockTransferController::class, 'index']);
        Route::get('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'show']);
        Route::post('/stock-transfers', [StockTransferController::class, 'store']);
        Route::patch('/stock-transfers/{stockTransfer}', [StockTransferController::class, 'update']);

        Route::get('/stock-movements', [StockMovementController::class, 'index']);
        Route::get('/stock-movements/{stockMovement}', [StockMovementController::class, 'show']);
    });
});
