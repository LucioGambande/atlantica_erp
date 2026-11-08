<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. FK solo hacia `logistics_rates` (tabla nueva de esta
// fase). Nunca se actualiza el `price` de una fila existente: "editar una
// tarifa" = cerrar la versión vigente (valid_until) + insertar una fila nueva.
// Ver App\Services\LogisticsRateVersionService. Sin soft deletes: una versión
// de precio no se borra ni se edita in-place.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_rate_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('logistics_rate_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->decimal('price', 10, 2);
            $table->string('currency', 3);
            $table->boolean('vat_included')->default(false);
            $table->decimal('minimum_cost', 10, 2)->nullable();
            $table->json('surcharges')->nullable();
            $table->text('conditions')->nullable();
            $table->string('source_document_path')->nullable();
            $table->date('valid_from');
            $table->date('valid_until')->nullable();
            $table->timestamps();

            $table->index(['logistics_rate_id', 'valid_from']);
            $table->index(['logistics_rate_id', 'valid_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_rate_versions');
    }
};
