<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Aditiva. Distingue tarifas "flat" (precio fijo por unidad, ya soportado)
// de tarifas "matrix" (precio por tramo de peso/volumen y zona de destino,
// ver logistics_rate_zones/logistics_rate_weight_brackets/logistics_rate_prices
// en migraciones posteriores). `price` pasa a ser nullable porque las
// tarifas matrix no tienen un precio único por versión.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->string('pricing_type')->default('flat')->after('unit_of_measure');
        });

        Schema::table('logistics_rate_versions', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('logistics_rate_versions', function (Blueprint $table): void {
            $table->decimal('price', 10, 2)->nullable(false)->change();
        });

        Schema::table('logistics_rates', function (Blueprint $table): void {
            $table->dropColumn('pricing_type');
        });
    }
};
