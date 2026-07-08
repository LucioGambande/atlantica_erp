<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Índices aditivos para acelerar filtros/ordenamientos en Filament.
 * No modifica datos ni columnas: solo agrega índices sobre columnas existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->index('status', 'invoices_status_index');
            $table->index('issued_at', 'invoices_issued_at_index');
            $table->index('document_type', 'invoices_document_type_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->index('paid_at', 'payments_paid_at_index');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->index('tax_id', 'customers_tax_id_index');
            $table->index('balance', 'customers_balance_index');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex('invoices_status_index');
            $table->dropIndex('invoices_issued_at_index');
            $table->dropIndex('invoices_document_type_index');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropIndex('payments_paid_at_index');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropIndex('customers_tax_id_index');
            $table->dropIndex('customers_balance_index');
        });
    }
};
