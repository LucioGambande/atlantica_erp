<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('detail_type');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('bank_transfer_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number');
            $table->string('bank_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('card_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->string('authorization_code')->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('cash_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('bizum_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->string('operation_code')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
        });

        Schema::create('cheque_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->string('cheque_number');
            $table->string('bank_name')->nullable();
            $table->timestamps();
        });

        Schema::create('generic_payment_details', function (Blueprint $table): void {
            $table->id();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generic_payment_details');
        Schema::dropIfExists('cheque_payment_details');
        Schema::dropIfExists('bizum_payment_details');
        Schema::dropIfExists('cash_payment_details');
        Schema::dropIfExists('card_payment_details');
        Schema::dropIfExists('bank_transfer_payment_details');
        Schema::dropIfExists('payment_methods');
    }
};
