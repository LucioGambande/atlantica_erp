<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. FK solo hacia `logistics_rate_versions`. Tramos de
// peso por VERSIÓN (no por tarifa): si una tarifa nueva redefine los
// tramos, los viejos quedan intactos en la versión cerrada, preservando el
// historial exacto de la tarifa anterior.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rate_weight_brackets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_rate_version_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('up_to_kg', 10, 2);
            $table->unsignedInteger('sort_order')->nullable();
            $table->timestamps();

            $table->unique(['logistics_rate_version_id', 'up_to_kg']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rate_weight_brackets');
    }
};
