<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseInvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_received_purchase_and_moves_stock(): void
    {
        $this->authenticateWith('manage purchases');

        $supplier = Supplier::create(['name' => 'Proveedor Test']);
        $warehouse = Warehouse::create(['name' => 'Depósito Test', 'type' => 'own', 'is_active' => true]);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-PI-1', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 0]);

        $response = $this->postJson('/api/v1/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'warehouse_id' => $warehouse->id,
            'document_number' => 'COMPRA-API-1',
            'status' => 'received',
            'total_amount' => 100,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 20, 'unit_price' => 5, 'total_price' => 100],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertSame(20, $product->fresh()->stock);
        $this->assertDatabaseHas('purchase_invoices', ['document_number' => 'COMPRA-API-1', 'stock_movements_recorded' => true]);
    }

    public function test_it_requires_at_least_one_item(): void
    {
        $this->authenticateWith('manage purchases');

        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $response = $this->postJson('/api/v1/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-API-2',
            'total_amount' => 100,
            'items' => [],
        ]);

        $response->assertStatus(422);
    }

    public function test_document_number_must_be_unique(): void
    {
        $this->authenticateWith('manage purchases');

        $supplier = Supplier::create(['name' => 'Proveedor Test']);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-PI-2', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 0]);

        $this->postJson('/api/v1/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-API-DUP',
            'total_amount' => 100,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]],
        ])->assertStatus(201);

        $response = $this->postJson('/api/v1/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-API-DUP',
            'total_amount' => 100,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]],
        ]);

        $response->assertStatus(422);
    }

    public function test_updating_status_to_draft_reverses_stock(): void
    {
        $this->authenticateWith('manage purchases');

        $supplier = Supplier::create(['name' => 'Proveedor Test']);
        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-PI-3', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 0]);

        $created = $this->postJson('/api/v1/purchase-invoices', [
            'supplier_id' => $supplier->id,
            'document_number' => 'COMPRA-API-3',
            'status' => 'received',
            'total_amount' => 50,
            'items' => [['product_id' => $product->id, 'quantity' => 10, 'unit_price' => 5, 'total_price' => 50]],
        ])->json('data');

        $this->assertSame(10, $product->fresh()->stock);

        $this->patchJson("/api/v1/purchase-invoices/{$created['id']}", ['status' => 'draft'])
            ->assertStatus(200);

        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_it_requires_the_manage_purchases_permission(): void
    {
        $this->authenticateWith('manage stock');

        $this->getJson('/api/v1/purchase-invoices')->assertStatus(403);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
