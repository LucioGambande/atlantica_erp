<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\User;
use App\Services\StockService;
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

    public function test_it_cancels_an_invoice_and_reverses_stock_via_api(): void
    {
        Invoice::skipSequenceValidation(true);
        $this->authenticateWith('manage invoices');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $product = Product::create([
            'name' => 'Producto Test',
            'sku' => 'TEST-API-750',
            'purchase_price' => 0,
            'sale_price' => 10,
            'stock' => 50,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-API-0002',
            'document_type' => 'invoice',
            'is_fiscal_document' => true,
            'status' => 'issued',
            'total_amount' => 0,
            'generates_stock_movement' => true,
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 6,
            'unit_price' => 10,
            'discount_percent' => 0,
            'total_price' => 60,
        ]);

        app(StockService::class)->applyStockFromInvoice($invoice->fresh());
        $this->assertSame(44, $product->fresh()->stock);

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/cancel");

        $response->assertStatus(201)
            ->assertJsonPath('data.document_type', 'credit_note')
            ->assertJsonPath('data.credited_invoice_id', $invoice->id);

        $this->assertSame(50, $product->fresh()->stock, 'La cancelación debería devolver el stock vendido.');
        $this->assertNotSame(0.0, (float) $response->json('data.total_amount'));
    }

    public function test_it_creates_a_partial_credit_note_via_api(): void
    {
        Invoice::skipSequenceValidation(true);
        $this->authenticateWith('manage invoices');

        $customer = Customer::create(['name' => 'Cliente Test', 'customer_type' => 'horeca']);
        $product = Product::create([
            'name' => 'Producto Test',
            'sku' => 'TEST-API-751',
            'purchase_price' => 0,
            'sale_price' => 10,
            'stock' => 50,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-API-0003',
            'document_type' => 'invoice',
            'is_fiscal_document' => true,
            'status' => 'issued',
            'total_amount' => 0,
            'generates_stock_movement' => true,
            'issued_at' => now(),
        ]);

        $item = $invoice->invoiceItems()->create([
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => 6,
            'unit_price' => 10,
            'discount_percent' => 0,
            'total_price' => 60,
        ]);

        app(StockService::class)->applyStockFromInvoice($invoice->fresh());

        $response = $this->postJson("/api/v1/invoices/{$invoice->id}/credit-notes", [
            'lines' => [
                ['invoice_item_id' => $item->id, 'quantity' => 2],
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.document_type', 'credit_note');

        $this->assertSame(46, $product->fresh()->stock, 'La devolución parcial debería devolver solo las unidades indicadas.');
        $this->assertFalse($invoice->fresh()->isCancelled(), 'La factura original no debería quedar cancelada.');
    }

    private function authenticateWith(string $permission): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->syncPermissions([$permission]);

        Sanctum::actingAs($user, ['*']);
    }
}
