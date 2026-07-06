<?php

use App\Http\Controllers\AccountStatementPrintController;
use App\Http\Controllers\InvoicePrintController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect('/admin')
        : redirect('/admin/login');
});

Route::get('/dashboard', function () {
    return redirect('/admin');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::middleware('role_or_permission:print invoices|manage invoices')->group(function (): void {
        Route::get('/admin/invoices/{invoice}/print', [InvoicePrintController::class, 'show'])
            ->name('invoices.print');
        Route::get('/admin/invoices/print/range', [InvoicePrintController::class, 'range'])
            ->name('invoices.print.range');
    });

    Route::middleware('role_or_permission:manage customers|manage invoices')->group(function (): void {
        Route::get('/admin/customers/{customer}/statement/print', [AccountStatementPrintController::class, 'show'])
            ->name('customers.statement.print');
    });
});

require __DIR__.'/auth.php';
