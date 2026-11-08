<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_warehouse_can_be_created_with_its_type(): void
    {
        $warehouse = Warehouse::create([
            'name' => 'Depósito Barcelona',
            'type' => 'own',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Depósito Barcelona',
            'type' => 'own',
        ]);
    }

    public function test_an_inactive_warehouse_is_excluded_from_the_active_scope(): void
    {
        // La migration de backfill ya deja un almacén centinela activo en la
        // base: filtramos por nombre para no depender de ese dato de fondo.
        Warehouse::create(['name' => 'Activo', 'type' => 'own', 'is_active' => true]);
        Warehouse::create(['name' => 'Inactivo', 'type' => 'own', 'is_active' => false]);

        $names = Warehouse::query()->active()->pluck('name')->all();

        $this->assertContains('Activo', $names);
        $this->assertNotContains('Inactivo', $names);
    }
}
