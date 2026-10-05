<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->boolean('is_fiscal_document')->default(true)->after('document_type');
            $table->foreignId('consolidated_into_invoice_id')
                ->nullable()
                ->after('credited_invoice_id')
                ->constrained('invoices')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('consolidated_into_invoice_id');
            $table->dropColumn('is_fiscal_document');
        });
    }
};
