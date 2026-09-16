<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_payment_reverts_the_invoice_and_leaves_no_ledger_trace(): void
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
            'total_amount' => 121.00,
            'issued_at' => now(),
        ]);

        $paymentMethod = PaymentMethod::query()->where('slug', 'manual')->firstOrFail();

        $customer->refresh();
        $this->assertSame(121.0, (float) $customer->balance);

        $payment = app(PaymentService::class)->registerInvoicePayment(
            invoice: $invoice,
            paymentMethodId: $paymentMethod->id,
        );

        $invoice->refresh();
        $customer->refresh();

        $this->assertSame('paid', $invoice->status);
        $this->assertSame(0.0, (float) $customer->balance);
        $this->assertDatabaseHas('ledger_entries', [
            'reference_type' => $payment->getMorphClass(),
            'reference_id' => $payment->id,
            'type' => LedgerEntry::TYPE_PAYMENT,
        ]);

        $paymentId = $payment->id;
        $referenceType = $payment->getMorphClass();
        $payment->delete();

        $invoice->refresh();
        $customer->refresh();

        $this->assertDatabaseMissing('payments', ['id' => $paymentId]);
        $this->assertDatabaseMissing('payment_allocations', ['payment_id' => $paymentId]);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame(121.0, (float) $customer->balance);
        $this->assertDatabaseMissing('ledger_entries', [
            'reference_type' => $referenceType,
            'reference_id' => $paymentId,
        ]);
    }
}
