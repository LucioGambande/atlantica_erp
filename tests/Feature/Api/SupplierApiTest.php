<?php

namespace Tests\Feature\Api;

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupplierApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_supplier(): void
    {
        $this->authenticateWith('manage purchases');

        $response = $this->postJson('/api/v1/suppliers', [
            'name' => "Wine O'Clock Importaciones SL",
            'tax_id' => 'B13888649',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('suppliers', ['tax_id' => 'B13888649']);
    }

    public function test_it_lists_and_shows_suppliers(): void
    {
        $this->authenticateWith('manage purchases');

        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $this->getJson('/api/v1/suppliers')->assertStatus(200);
        $this->getJson("/api/v1/suppliers/{$supplier->id}")->assertStatus(200)
            ->assertJsonPath('data.name', 'Proveedor Test');
    }

    public function test_name_is_required(): void
    {
        $this->authenticateWith('manage purchases');

        $this->postJson('/api/v1/suppliers', [])->assertStatus(422);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
