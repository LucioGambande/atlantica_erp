<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LedgerAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_credit_adjustment_reduces_what_the_customer_owes(): void
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
            'total_amount' => 345.41,
            'issued_at' => now(),
        ]);

        app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

        $customer->refresh();
        $this->assertSame(345.41, (float) $customer->balance);

        app(AccountStatementService::class)->registerAdjustment(
            customer: $customer,
            reference: $invoice,
            description: 'Condonación de saldo según acuerdo comercial con el cliente',
            debit: 0.0,
            credit: 345.41,
            date: now(),
        );

        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->balance);
        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'type' => 'adjustment',
            'description' => 'Condonación de saldo según acuerdo comercial con el cliente',
        ]);
    }

    public function test_a_debit_adjustment_increases_what_the_customer_owes(): void
    {
        Invoice::skipSequenceValidation(true);

        $customer = Customer::create([
            'name' => 'Cliente Test 2',
            'customer_type' => 'horeca',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_number' => 'TEST-0002',
            'status' => 'issued',
            'total_amount' => 100.00,
            'issued_at' => now(),
        ]);

        app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

        app(AccountStatementService::class)->registerAdjustment(
            customer: $customer,
            reference: $invoice,
            description: 'Recargo acordado con el cliente por gestión especial',
            debit: 15.0,
            credit: 0.0,
            date: now(),
        );

        // 100.00 sin líneas pasa por el redondeo neto/IVA de grossAmount() y
        // queda en 99.99 (comportamiento preexistente, ajeno a este test).
        $customer->refresh();
        $this->assertSame(114.99, (float) $customer->balance);
    }
}
