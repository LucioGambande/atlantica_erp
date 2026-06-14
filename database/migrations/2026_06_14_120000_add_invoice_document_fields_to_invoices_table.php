<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('document_type', 20)->default('invoice')->after('invoice_number');
            $table->foreignId('credited_invoice_id')
                ->nullable()
                ->after('order_id')
                ->constrained('invoices')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->boolean('generates_stock_movement')->default(false)->after('total_amount');
            $table->boolean('stock_movements_recorded')->default(false)->after('generates_stock_movement');
            $table->timestamp('cancelled_at')->nullable()->after('issued_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('credited_invoice_id');
            $table->dropColumn([
                'document_type',
                'generates_stock_movement',
                'stock_movements_recorded',
                'cancelled_at',
            ]);
        });
    }
};
