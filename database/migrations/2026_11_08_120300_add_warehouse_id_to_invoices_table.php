<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public const DEFAULT_WAREHOUSE_NAME = 'Almacén Central (pendiente de renombrar)';

    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->foreignId('warehouse_id')
                ->nullable()
                ->after('order_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        DB::table('invoices')
            ->whereNull('warehouse_id')
            ->update(['warehouse_id' => $this->defaultWarehouseId()]);
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
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
