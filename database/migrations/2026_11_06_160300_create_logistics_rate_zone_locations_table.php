<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. Pivot entre zonas de tarifa y ubicaciones del
// catálogo. `logistics_rate_id` viene denormalizado a propósito (es
// derivable vía logistics_rate_zone_id -> logistics_rate_zones.logistics_rate_id)
// para poder expresar con un UNIQUE de base de datos la regla "una ubicación
// cae en una sola zona por tarifa" sin depender únicamente de validación en
// la aplicación.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rate_zone_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_rate_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('logistics_rate_zone_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('logistics_location_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->timestamps();

            $table->unique(['logistics_rate_id', 'logistics_location_id']);
            $table->index('logistics_rate_zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rate_zone_locations');
    }
};
