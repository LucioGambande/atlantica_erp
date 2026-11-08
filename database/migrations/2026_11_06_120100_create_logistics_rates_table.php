<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. FK solo hacia `logistics_providers` (tabla nueva de esta
// misma fase). Esta tabla es la definición de la tarifa, sin precio: el precio
// vive versionado en `logistics_rate_versions`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_provider_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('name');
            $table->string('service_type');
            $table->string('origin')->nullable();
            $table->string('destination')->nullable();
            $table->string('unit_of_measure');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['logistics_provider_id', 'is_active']);
            $table->index('service_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rates');
    }
};
