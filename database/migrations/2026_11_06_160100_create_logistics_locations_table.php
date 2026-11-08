<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva, sin FK hacia ninguna tabla existente. Catálogo
// maestro de ubicaciones (provincias/ciudades/países) reutilizable entre
// todas las tarifas logísticas — no se borra ni se recrea por tarifa.
// Sin soft deletes: es un catálogo, no un registro histórico.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_locations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('country', 2);
            $table->string('category');
            $table->decimal('volumetric_kg_per_m3', 6, 2);
            $table->timestamps();

            $table->unique(['name', 'country']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_locations');
    }
};
