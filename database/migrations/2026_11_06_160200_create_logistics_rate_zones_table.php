<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. FK solo hacia `logistics_rates`. Las zonas son
// estables a nivel de LogisticsRate (no versionadas): representan la
// geografía del contrato con el transportista, no el precio. Si un
// transportista redefine sus zonas, se crea una LogisticsRate nueva en vez
// de editar/borrar zonas existentes, para no perder el historial de precios
// de las zonas viejas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rate_zones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_rate_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->timestamps();

            $table->unique(['logistics_rate_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rate_zones');
    }
};
