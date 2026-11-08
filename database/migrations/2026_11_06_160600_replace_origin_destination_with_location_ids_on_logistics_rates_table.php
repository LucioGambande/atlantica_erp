<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Aditiva salvo por el drop de las dos columnas de texto libre `origin`/
// `destination` (sin datos reales en producción). Las reemplaza por FKs al
// catálogo `logistics_locations`, usadas solo por tarifas "flat" punto a
// punto; las tarifas "matrix" usan el sistema de zonas (logistics_rate_zones
// y siguientes), no estas dos columnas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->dropColumn(['origin', 'destination']);
        });

        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->foreignId('origin_location_id')
                ->nullable()
                ->after('pricing_type')
                ->constrained('logistics_locations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('destination_location_id')
                ->nullable()
                ->after('origin_location_id')
                ->constrained('logistics_locations')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('origin_location_id');
            $table->dropConstrainedForeignId('destination_location_id');
        });

        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
        });
    }
};
