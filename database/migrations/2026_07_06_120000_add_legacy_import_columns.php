<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->string('legacy_id', 50)->nullable()->unique()->after('id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('legacy_invoice_number', 50)->nullable()->unique()->after('invoice_number');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_line_id')->nullable()->after('invoice_id');
            $table->unique(['invoice_id', 'legacy_line_id']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_payment_id')->nullable()->after('id');
            $table->unique('legacy_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['legacy_payment_id']);
            $table->dropColumn('legacy_payment_id');
        });

        Schema::table('invoice_items', function (Blueprint $table): void {
            $table->dropUnique(['invoice_id', 'legacy_line_id']);
            $table->dropColumn('legacy_line_id');
        });

        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropUnique(['legacy_invoice_number']);
            $table->dropColumn('legacy_invoice_number');
        });

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropUnique(['legacy_id']);
            $table->dropColumn('legacy_id');
        });
    }
};
