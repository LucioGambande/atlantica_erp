<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\AccountStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettleLedgerToZeroTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_settles_a_positive_balance_to_zero_with_an_adjustment(): void
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

        $this->artisan('ledger:settle-to-zero', [
            'customer_id' => $customer->id,
            'description' => 'Condonación de saldo según acuerdo comercial con el cliente',
            '--force' => true,
        ])->assertSuccessful();

        $customer->refresh();
        $this->assertSame(0.0, (float) $customer->balance);

        $this->assertDatabaseHas('ledger_entries', [
            'customer_id' => $customer->id,
            'type' => 'adjustment',
            'description' => 'Condonación de saldo según acuerdo comercial con el cliente',
        ]);
    }

    public function test_it_does_nothing_when_balance_is_already_zero(): void
    {
        $customer = Customer::create([
            'name' => 'Cliente Sin Deuda',
            'customer_type' => 'horeca',
        ]);

        $this->artisan('ledger:settle-to-zero', [
            'customer_id' => $customer->id,
            'description' => 'No debería aplicarse',
            '--force' => true,
        ])->assertSuccessful();

        $this->assertDatabaseMissing('ledger_entries', [
            'customer_id' => $customer->id,
        ]);
    }
}
