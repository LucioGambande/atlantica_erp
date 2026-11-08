<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tabla nueva, aditiva. No modifica `suppliers` ni ninguna tabla existente:
// LogisticsProvider es una entidad independiente, sin FK hacia `suppliers`.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logistics_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('type');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logistics_providers');
    }
};
