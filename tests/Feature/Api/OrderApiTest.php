<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_order_with_items_and_reduces_stock(): void
    {
        $this->authenticateWith('manage orders');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $product = Product::create([
            'name' => 'Producto Test',
            'sku' => 'ORD-API-750',
            'purchase_price' => 0,
            'sale_price' => 10,
            'stock' => 50,
        ]);

        $response = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 6, 'unit_price' => 10],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.customer.id', $customer->id);

        $this->assertDatabaseHas('orders', ['customer_id' => $customer->id]);
        $this->assertSame(44, $product->fresh()->stock);
    }

    public function test_it_lists_and_shows_orders(): void
    {
        $this->authenticateWith('manage orders');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $order = Order::create(['customer_id' => $customer->id, 'status' => 'pending', 'total_amount' => 0]);

        $this->getJson('/api/v1/orders')->assertStatus(200);
        $this->getJson("/api/v1/orders/{$order->id}")->assertStatus(200)
            ->assertJsonPath('data.id', $order->id);
    }

    public function test_it_requires_the_manage_orders_permission(): void
    {
        $this->authenticateWith('manage stock');

        $this->getJson('/api/v1/orders')->assertStatus(403);
    }

    public function test_it_adds_an_item_to_an_existing_order_and_reduces_stock(): void
    {
        $this->authenticateWith('manage orders');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $existingProduct = Product::create([
            'name' => 'Producto Existente',
            'sku' => 'ORD-ITEM-A-750',
            'purchase_price' => 0,
            'sale_price' => 10,
            'stock' => 50,
        ]);
        $newProduct = Product::create([
            'name' => 'Producto Nuevo',
            'sku' => 'ORD-ITEM-B-750',
            'purchase_price' => 0,
            'sale_price' => 20,
            'stock' => 10,
        ]);

        $order = $this->postJson('/api/v1/orders', [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $existingProduct->id, 'quantity' => 2, 'unit_price' => 10],
            ],
        ])->json('data');

        $response = $this->patchJson("/api/v1/orders/{$order['id']}/items", [
            'product_id' => $newProduct->id,
            'quantity' => 3,
            'unit_price' => 20,
        ]);

        $response->assertStatus(200);
        $this->assertSame(80.0, (float) $response->json('data.total_amount'));
        $this->assertSame(7, $newProduct->fresh()->stock);
        $this->assertDatabaseHas('order_items', [
            'order_id' => $order['id'],
            'product_id' => $newProduct->id,
            'quantity' => 3,
        ]);
    }

    public function test_it_rejects_adding_an_item_without_enough_stock(): void
    {
        $this->authenticateWith('manage orders');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $product = Product::create([
            'name' => 'Sin Stock',
            'sku' => 'ORD-ITEM-C-750',
            'purchase_price' => 0,
            'sale_price' => 25,
            'stock' => 0,
        ]);
        $order = Order::create(['customer_id' => $customer->id, 'status' => 'pending', 'total_amount' => 0]);

        $this->patchJson("/api/v1/orders/{$order->id}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25,
        ])->assertStatus(422);

        $this->patchJson("/api/v1/orders/{$order->id}/items", [
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 25,
            'allow_negative_stock' => true,
        ])->assertStatus(200);

        $this->assertSame(-2, $product->fresh()->stock);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
