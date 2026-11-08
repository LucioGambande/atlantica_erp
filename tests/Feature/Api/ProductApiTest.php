<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_product_starting_at_zero_stock(): void
    {
        $this->authenticateWith('manage products');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Queso',
            'sku' => 'SKU-API-1',
            'purchase_price' => 5,
            'sale_price' => 10,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-API-1', 'stock' => 0]);
    }

    public function test_it_ignores_a_stock_value_sent_on_create(): void
    {
        $this->authenticateWith('manage products');

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Queso',
            'sku' => 'SKU-API-2',
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => 999,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['sku' => 'SKU-API-2', 'stock' => 0]);
    }

    public function test_sku_must_be_unique(): void
    {
        $this->authenticateWith('manage products');

        Product::create(['name' => 'Queso', 'sku' => 'SKU-DUP', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 0]);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Otro queso',
            'sku' => 'SKU-DUP',
            'purchase_price' => 5,
            'sale_price' => 10,
        ]);

        $response->assertStatus(422);
    }

    public function test_it_updates_a_product_without_touching_stock(): void
    {
        $this->authenticateWith('manage products');

        $product = Product::create(['name' => 'Queso', 'sku' => 'SKU-API-3', 'purchase_price' => 5, 'sale_price' => 10, 'stock' => 42]);

        $this->putJson("/api/v1/products/{$product->id}", ['sale_price' => 12, 'stock' => 0])
            ->assertStatus(200)
            ->assertJsonPath('data.sale_price', '12.00');

        $this->assertSame(42, $product->fresh()->stock);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
