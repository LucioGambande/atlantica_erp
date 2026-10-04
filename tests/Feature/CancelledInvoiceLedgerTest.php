<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AccountStatementService;
use App\Services\InvoiceService;
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
}
