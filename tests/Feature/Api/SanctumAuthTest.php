<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_without_a_token_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/warehouses')->assertStatus(401);
    }

    public function test_a_token_without_the_right_permission_is_forbidden(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions(['manage customers']);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/warehouses')->assertStatus(403);
    }

    public function test_a_token_with_the_right_permission_is_authorized(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions(['manage stock']);

        Sanctum::actingAs($user, ['*']);

        $this->getJson('/api/v1/warehouses')->assertStatus(200);
    }
}
