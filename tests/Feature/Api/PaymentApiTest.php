<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_a_payment_against_an_invoice(): void
    {
        Invoice::skipSequenceValidation(true);
        $this->authenticateWith('manage invoices');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-PAY-0001',
            'document_type' => 'invoice',
            'is_fiscal_document' => true,
            'status' => 'issued',
            'total_amount' => 0,
            'issued_at' => now(),
        ]);
        $invoice->invoiceItems()->create([
            'description' => 'Producto de prueba',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_percent' => 0,
            'total_price' => 100,
        ]);
        $invoice->recalculateTotalFromItems();

        $method = PaymentMethod::create([
            'name' => 'Genérico Test',
            'detail_type' => 'generic',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $response = $this->postJson('/api/v1/payments', [
            'customer_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'amount' => $invoice->fresh()->grossAmount(),
            'payment_method_id' => $method->id,
            'paid_at' => now()->toDateTimeString(),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('payments', ['customer_id' => $customer->id, 'invoice_id' => $invoice->id]);
    }

    public function test_it_lists_and_shows_payments(): void
    {
        Invoice::skipSequenceValidation(true);
        $this->authenticateWith('manage invoices');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $method = PaymentMethod::create([
            'name' => 'Genérico Test',
            'detail_type' => 'generic',
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'payment_method_id' => $method->id,
            'amount' => 50,
            'paid_at' => now(),
        ]);

        $this->getJson('/api/v1/payments')->assertStatus(200);
        $this->getJson("/api/v1/payments/{$payment->id}")->assertStatus(200)
            ->assertJsonPath('data.id', $payment->id);
    }

    public function test_it_lists_active_payment_methods(): void
    {
        $this->authenticateWith('manage invoices');

        PaymentMethod::create(['name' => 'Método Test Activo', 'detail_type' => 'generic', 'is_active' => true, 'sort_order' => 99]);
        PaymentMethod::create(['name' => 'Método Test Inactivo', 'detail_type' => 'generic', 'is_active' => false, 'sort_order' => 100]);

        $response = $this->getJson('/api/v1/payment-methods');

        $response->assertStatus(200)
            ->assertJsonFragment(['name' => 'Método Test Activo'])
            ->assertJsonMissing(['name' => 'Método Test Inactivo']);
    }

    public function test_it_requires_the_manage_invoices_permission(): void
    {
        $this->authenticateWith('manage stock');

        $this->getJson('/api/v1/payments')->assertStatus(403);
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
