<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. La celda de la matriz: cruza un tramo de peso
// (propio de una versión de tarifa) con una zona (propia de la tarifa) para
// dar el precio. FKs solo hacia tablas nuevas de este mismo módulo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rate_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_rate_weight_bracket_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('logistics_rate_zone_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('price', 10, 2);
            $table->timestamps();

            $table->unique(['logistics_rate_weight_bracket_id', 'logistics_rate_zone_id'], 'logistics_rate_prices_bracket_zone_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rate_prices');
    }
};
