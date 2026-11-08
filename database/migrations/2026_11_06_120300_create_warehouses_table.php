<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. FK nullable solo hacia `logistics_providers` (tabla
// nueva de esta fase). Sin control de stock en esta fase: es solo
// estructura/catálogo de almacenes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('address')->nullable();
            $table->string('type');
            $table->foreignId('logistics_provider_id')
                ->nullable()
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
