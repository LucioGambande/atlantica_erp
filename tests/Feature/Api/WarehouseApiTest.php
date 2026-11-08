<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WarehouseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_and_lists_warehouses(): void
    {
        $this->authenticateWith('manage stock');

        $response = $this->postJson('/api/v1/warehouses', ['name' => 'Depósito Madrid', 'type' => 'own']);
        $response->assertStatus(201);

        $this->getJson('/api/v1/warehouses')->assertStatus(200);
    }

    public function test_it_rejects_an_invalid_type(): void
    {
        $this->authenticateWith('manage stock');

        $this->postJson('/api/v1/warehouses', ['name' => 'Depósito', 'type' => 'invalido'])
            ->assertStatus(422);
    }

    public function test_it_updates_a_warehouse(): void
    {
        $this->authenticateWith('manage stock');

        $warehouse = Warehouse::create(['name' => 'Depósito', 'type' => 'own', 'is_active' => true]);

        $this->putJson("/api/v1/warehouses/{$warehouse->id}", ['is_active' => false])
            ->assertStatus(200)
            ->assertJsonPath('data.is_active', false);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
