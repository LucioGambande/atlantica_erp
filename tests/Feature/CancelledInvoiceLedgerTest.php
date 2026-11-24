<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Product;
use App\Services\AccountStatementService;
use App\Services\InvoiceService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelledInvoiceLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuilding_the_ledger_does_not_resurrect_a_cancelled_invoices_debt(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Test',
            'customer_type' => 'horeca',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0001',
            'status' => 'issued',
            'total_amount' => 0,
            'issued_at' => now(),
        ]);

        $invoice->invoiceItems()->create([
            'description' => 'Producto de prueba',
            'quantity' => 6,
            'unit_price' => 8.92,
            'discount_percent' => 5,
            'total_price' => 50.84,
        ]);

        $invoice->recalculateTotalFromItems();

        app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

        $customer->refresh();
        $this->assertGreaterThan(0.0, (float) $customer->balance);

        app(InvoiceService::class)->cancelInvoice($invoice);

        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->balance, 'Cancelar la factura debería dejar el saldo en 0.');

        // Reconstruir el libro mayor (botón "Importar movimientos") no debe revivir
        // la deuda de una factura cancelada.
        app(AccountStatementService::class)->rebuildLedger($customer);

        $customer->refresh();
        $this->assertSame(
            0.0,
            (float) $customer->balance,
            'rebuildLedger() no debe volver a sumar la deuda de una factura cancelada.',
        );
    }

    public function test_credit_note_gross_amount_is_derived_from_its_items_even_if_total_amount_is_stale(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Test 2',
            'customer_type' => 'horeca',
        ]);

        $creditNote = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0002',
            'document_type' => 'credit_note',
            'status' => 'issued',
            // Simula el estado roto observado en producción: total_amount
            // quedó en 0 aunque la nota de crédito tiene líneas reales.
            'total_amount' => 0,
            'issued_at' => now(),
        ]);

        $creditNote->invoiceItems()->create([
            'description' => 'Producto de prueba',
            'quantity' => 6,
            'unit_price' => -8.92,
            'discount_percent' => 5,
            'total_price' => -50.84,
        ]);

        $this->assertSame(61.52, round($creditNote->fresh(['invoiceItems'])->grossAmount(), 2));
    }

    public function test_cancelling_an_invoice_reverses_stock_and_sets_the_credit_notes_total(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Stock Test',
            'customer_type' => 'horeca',
        ]);

        $product = Product::create([
            'name' => 'Producto Stock Test',
            'sku' => 'STK-TEST-750',
            'purchase_price' => 0,
            'sale_price' => 10,
            'stock' => 50,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0003',
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

        $creditNote = app(InvoiceService::class)->cancelInvoice($invoice->fresh());

        $this->assertSame(
            50,
            $product->fresh()->stock,
            'Cancelar la factura debería devolver a stock las unidades vendidas.',
        );
        $this->assertSame(
            -72.6,
            round((float) $creditNote->fresh()->total_amount, 2),
            'La nota de crédito no debería quedar en total_amount = 0 teniendo líneas reales.',
        );
    }
}
