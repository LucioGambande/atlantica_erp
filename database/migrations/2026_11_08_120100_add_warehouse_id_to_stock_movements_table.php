<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nombre fijo del almacén centinela al que se backfillean los
     * movimientos/documentos históricos, sin almacén propio. Lo buscan
     * (en vez de asumir "la primera fila") esta migration y las 3
     * siguientes que backfillean orders/invoices/purchase_invoices: deben
     * desplegarse juntas, en el mismo deploy.
     */
    public const DEFAULT_WAREHOUSE_NAME = 'Almacén Central (pendiente de renombrar)';

    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('lot_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->index(['warehouse_id', 'product_id']);
        });

        $defaultWarehouseId = $this->defaultWarehouseId();

        DB::table('stock_movements')
            ->whereNull('warehouse_id')
            ->update(['warehouse_id' => $defaultWarehouseId]);
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('warehouse_id');
        });
    }

    protected function defaultWarehouseId(): int
    {
        $id = DB::table('warehouses')->where('name', self::DEFAULT_WAREHOUSE_NAME)->value('id');

        if ($id !== null) {
            return $id;
        }

        return DB::table('warehouses')->insertGetId([
            'name' => self::DEFAULT_WAREHOUSE_NAME,
            'type' => 'own',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
