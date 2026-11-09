<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\StockSanitizerService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillInvoiceStockMovementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_movement_for_a_legacy_invoice_dated_with_issued_at(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 0);
        $customer = $this->customer('horeca');
        $invoice = $this->invoice($customer, $product, quantity: 10, issuedAt: '2025-03-01 10:00:00');

        $stats = app(StockSanitizerService::class)->backfillMissingInvoiceMovements();

        $this->assertSame(1, $stats['invoices_found']);
        $this->assertSame(1, $stats['invoices_processed']);
        $this->assertSame(1, $stats['movements_created']);

        $movement = StockMovement::query()->where('reference_type', 'Invoice')->where('reference_id', $invoice->id)->sole();
        $this->assertSame('out', $movement->type);
        $this->assertSame(10, $movement->quantity);
        $this->assertSame('2025-03-01 10:00:00', $movement->created_at->format('Y-m-d H:i:s'));

        $this->assertSame(-10, $product->fresh()->stock);
        $this->assertTrue($invoice->fresh()->generates_stock_movement);
        $this->assertTrue($invoice->fresh()->stock_movements_recorded);
    }

    public function test_it_does_not_touch_invoices_that_already_have_a_movement(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 50);
        $customer = $this->customer('horeca');
        $invoice = $this->invoice($customer, $product, quantity: 5, issuedAt: '2025-01-01 00:00:00');
        $invoice->update(['generates_stock_movement' => true]);

        app(StockService::class)->applyStockFromInvoice($invoice);
        $this->assertSame(45, $product->fresh()->stock);

        $stats = app(StockSanitizerService::class)->backfillMissingInvoiceMovements();

        $this->assertSame(0, $stats['invoices_found']);
        $this->assertSame(1, StockMovement::query()->where('reference_type', 'Invoice')->count());
        $this->assertSame(45, $product->fresh()->stock);
    }

    public function test_it_skips_individual_customers_to_avoid_double_counting_the_order(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 20);
        $customer = $this->customer('individual');
        $this->invoice($customer, $product, quantity: 3, issuedAt: '2025-02-01 00:00:00');

        $stats = app(StockSanitizerService::class)->backfillMissingInvoiceMovements();

        $this->assertSame(1, $stats['invoices_found']);
        $this->assertSame(0, $stats['invoices_processed']);
        $this->assertSame(1, $stats['skipped_individual']);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(20, $product->fresh()->stock);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 0);
        $customer = $this->customer('horeca');
        $this->invoice($customer, $product, quantity: 7, issuedAt: '2025-04-01 00:00:00');

        $stats = app(StockSanitizerService::class)->backfillMissingInvoiceMovements(dryRun: true);

        $this->assertSame(1, $stats['invoices_found']);
        $this->assertTrue($stats['dry_run']);
        $this->assertSame(0, StockMovement::query()->count());
        $this->assertSame(0, $product->fresh()->stock);
    }

    public function test_it_correctly_nets_an_original_invoice_and_its_credit_note_when_both_are_missing_movements(): void
    {
        Invoice::skipSequenceValidation(true);

        $product = $this->product(stock: 0);
        $customer = $this->customer('horeca');
        $invoice = $this->invoice($customer, $product, quantity: 10, issuedAt: '2025-05-01 00:00:00');

        $creditNote = Invoice::create([
            'customer_id' => $customer->id,
            'credited_invoice_id' => $invoice->id,
            'invoice_number' => 'CN-TEST-0001',
            'document_type' => 'credit_note',
            'status' => 'issued',
            'total_amount' => 0,
            'generates_stock_movement' => true,
            'issued_at' => '2025-05-10 00:00:00',
        ]);
        $creditNote->invoiceItems()->create([
            'product_id' => $product->id,
            'description' => 'Devolución',
            'quantity' => 4,
            'unit_price' => -10,
            'discount_percent' => 0,
            'total_price' => -40,
        ]);

        app(StockSanitizerService::class)->backfillMissingInvoiceMovements();

        // Vendieron 10, devolvieron 4: neto -6 en stock.
        $this->assertSame(-6, $product->fresh()->stock);
    }

    private function product(int $stock): Product
    {
        return Product::create([
            'name' => 'Queso',
            'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5,
            'sale_price' => 10,
            'stock' => $stock,
        ]);
    }

    private function customer(string $customerType): Customer
    {
        return Customer::create(['name' => 'Cliente Test', 'customer_type' => $customerType]);
    }

    private function invoice(Customer $customer, Product $product, int $quantity, string $issuedAt): Invoice
    {
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'document_type' => 'invoice',
            'status' => 'issued',
            'total_amount' => 100,
            'generates_stock_movement' => false,
            'issued_at' => $issuedAt,
        ]);

        $invoice->invoiceItems()->create([
            'product_id' => $product->id,
            'description' => 'Queso',
            'quantity' => $quantity,
            'unit_price' => 10,
            'discount_percent' => 0,
            'total_price' => 10 * $quantity,
        ]);

        return $invoice->fresh();
    }
}
