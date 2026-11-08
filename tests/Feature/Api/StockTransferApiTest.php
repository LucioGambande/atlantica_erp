<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockTransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_completed_transfer_and_moves_stock(): void
    {
        $this->authenticateWith('manage stock');

        $origin = Warehouse::create(['name' => 'Barcelona', 'type' => 'own', 'is_active' => true]);
        $destination = Warehouse::create(['name' => 'Madrid', 'type' => 'own', 'is_active' => true]);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-ST-1', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 50]);

        $product->stockMovements()->create([
            'warehouse_id' => $origin->id,
            'type' => 'in',
            'quantity' => 50,
            'reference_type' => 'PurchaseInvoice',
            'reference_id' => 0,
        ]);

        $response = $this->postJson('/api/v1/stock-transfers', [
            'origin_warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'status' => 'completed',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 15],
            ],
        ]);

        $response->assertStatus(201);
        // Regresión: el JSON devuelto tiene que reflejar el estado posterior a
        // StockService, no el objeto en memoria de antes de sincronizar.
        $response->assertJsonPath('data.stock_movements_recorded', true);

        $service = app(StockService::class);
        $this->assertSame(35, $service->warehouseBalance($product, $origin->id));
        $this->assertSame(15, $service->warehouseBalance($product, $destination->id));
    }

    public function test_origin_and_destination_must_differ(): void
    {
        $this->authenticateWith('manage stock');

        $warehouse = Warehouse::create(['name' => 'Barcelona', 'type' => 'own', 'is_active' => true]);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-ST-2', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 10]);

        $response = $this->postJson('/api/v1/stock-transfers', [
            'origin_warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $warehouse->id,
            'status' => 'completed',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ]);

        $response->assertStatus(422);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
