<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('origin_warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('destination_warehouse_id')
                ->constrained('warehouses')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->boolean('stock_movements_recorded')->default(false);
            $table->timestamp('transferred_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        // Resguardo a nivel DB de que un traslado no apunte el mismo
        // almacén como origen y destino (también se valida en el form y
        // en el Service, que es lo que corre en SQLite para tests).
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE stock_transfers ADD CONSTRAINT stock_transfers_origin_destination_different '
                .'CHECK (origin_warehouse_id <> destination_warehouse_id)'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
