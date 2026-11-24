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

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
