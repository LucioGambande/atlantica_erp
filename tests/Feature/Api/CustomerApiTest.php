<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_customer(): void
    {
        $this->authenticateWith('manage customers');

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Restaurante Test',
            'customer_type' => 'horeca',
            'tax_id' => 'B12345678',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['name' => 'Restaurante Test']);
    }

    public function test_it_rejects_an_invalid_customer_type(): void
    {
        $this->authenticateWith('manage customers');

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Restaurante Test',
            'customer_type' => 'not-a-real-type',
        ]);

        $response->assertStatus(422);
    }

    public function test_it_lists_and_shows_customers(): void
    {
        $this->authenticateWith('manage customers');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);

        $this->getJson('/api/v1/customers')->assertStatus(200);
        $this->getJson("/api/v1/customers/{$customer->id}")->assertStatus(200)
            ->assertJsonPath('data.name', 'Cliente Test');
    }

    public function test_it_updates_a_customer(): void
    {
        $this->authenticateWith('manage customers');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);

        $this->putJson("/api/v1/customers/{$customer->id}", ['name' => 'Cliente Actualizado'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Cliente Actualizado');
    }

    public function test_it_requires_the_manage_customers_permission(): void
    {
        $this->authenticateWith('manage stock');

        $this->getJson('/api/v1/customers')->assertStatus(403);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
