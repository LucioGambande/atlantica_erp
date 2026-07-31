<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customer_interactions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['llamada', 'visita', 'mail']);
            $table->dateTime('happened_at');
            $table->text('notes');
            $table->enum('next_action_type', ['llamada', 'visita', 'mail'])->nullable();
            $table->date('next_action_at')->nullable();
            $table->string('next_action_notes')->nullable();
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable()->unique();
            $table->timestamps();

            $table->index(['customer_id', 'happened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};
