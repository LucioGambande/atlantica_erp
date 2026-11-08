<?php

namespace Tests\Feature;

use App\Models\LogisticsProvider;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_own_warehouse_has_no_provider(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Depósito Central',
            'type' => 'own',
        ]);

        $this->assertNull($warehouse->provider);
    }

    public function test_third_party_warehouse_belongs_to_a_provider(): void
    {
        $provider = LogisticsProvider::create([
            'name' => 'Almacenes Levante',
            'type' => 'warehouse',
        ]);

        $warehouse = Warehouse::create([
            'name' => 'Nave Valencia',
            'type' => 'third_party',
            'logistics_provider_id' => $provider->id,
        ]);

        $this->assertSame($provider->id, $warehouse->provider->id);
    }

    public function test_scope_active_only_returns_active_warehouses(): void
    {
        Warehouse::create(['name' => 'Activo', 'type' => 'own', 'is_active' => true]);
        Warehouse::create(['name' => 'Inactivo', 'type' => 'own', 'is_active' => false]);

        $this->assertSame(1, Warehouse::active()->count());
    }
}
