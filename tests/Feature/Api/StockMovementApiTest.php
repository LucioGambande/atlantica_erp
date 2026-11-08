<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StockMovementApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_movements_filtered_by_warehouse(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions(['manage stock']);
        Sanctum::actingAs($user, ['*']);

        $barcelona = Warehouse::create(['name' => 'Barcelona', 'type' => 'own', 'is_active' => true]);
        $madrid = Warehouse::create(['name' => 'Madrid', 'type' => 'own', 'is_active' => true]);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-SM-1', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 30]);

        $product->stockMovements()->create(['warehouse_id' => $barcelona->id, 'type' => 'in', 'quantity' => 10, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);
        $product->stockMovements()->create(['warehouse_id' => $madrid->id, 'type' => 'in', 'quantity' => 20, 'reference_type' => 'PurchaseInvoice', 'reference_id' => 0]);

        $response = $this->getJson("/api/v1/stock-movements?warehouse_id={$barcelona->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_the_endpoint_has_no_create_route(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions(['manage stock']);
        Sanctum::actingAs($user, ['*']);

        $this->postJson('/api/v1/stock-movements', [])->assertStatus(405);
    }
}
