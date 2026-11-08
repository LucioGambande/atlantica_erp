<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_and_shows_invoices(): void
    {
        Invoice::skipSequenceValidation(true);
        $this->authenticateWith('manage invoices');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-API-0001',
            'document_type' => 'invoice',
            'is_fiscal_document' => true,
            'status' => 'issued',
            'total_amount' => 100,
            'issued_at' => now(),
        ]);

        $this->getJson('/api/v1/invoices')->assertStatus(200);
        $this->getJson("/api/v1/invoices/{$invoice->id}")->assertStatus(200)
            ->assertJsonPath('data.invoice_number', 'TEST-API-0001');
    }

    public function test_it_requires_the_manage_invoices_permission(): void
    {
        $this->authenticateWith('manage stock');

        $this->getJson('/api/v1/invoices')->assertStatus(403);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
