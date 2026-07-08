<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank_transfer_payment_details', function (Blueprint $table): void {
            $table->string('transaction_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfer_payment_details', function (Blueprint $table): void {
            $table->string('transaction_number')->nullable(false)->change();
        });
    }
};
