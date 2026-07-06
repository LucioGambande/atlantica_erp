<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\AccountStatementService;
use App\Services\PaymentService;

class PaymentObserver
{
    /** @var list<int> */
    protected array $invoiceIdsToResync = [];

    public function __construct(
        protected AccountStatementService $accountStatementService,
        protected PaymentService $paymentService,
    ) {
    }

    public function created(Payment $payment): void
    {
        $payment->loadMissing('customer', 'invoice', 'paymentMethod', 'allocations.invoice');

        $this->accountStatementService->registerPayment($payment);
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged(['amount', 'paid_at', 'customer_id', 'invoice_id', 'payment_method_id'])) {
            return;
        }

        $payment->loadMissing('customer', 'invoice', 'paymentMethod', 'allocations.invoice');

        if ($payment->wasChanged('customer_id')) {
            $previousCustomerId = (int) $payment->getOriginal('customer_id');

            if ($previousCustomerId > 0) {
                $previousCustomer = Customer::query()->find($previousCustomerId);

                if ($previousCustomer !== null) {
                    $this->accountStatementService->rebuildLedger($previousCustomer);
                }
            }

            $this->accountStatementService->rebuildLedger($payment->customer);

            return;
        }

        $this->accountStatementService->syncPayment($payment);
    }

    public function deleting(Payment $payment): void
    {
        $this->invoiceIdsToResync = $payment->allocations()
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function deleted(Payment $payment): void
    {
        $payment->loadMissing('customer');

        $this->accountStatementService->registerPaymentReversal($payment);

        foreach ($this->invoiceIdsToResync as $invoiceId) {
            $this->paymentService->syncInvoicePaymentStatus($invoiceId);
        }

        $this->invoiceIdsToResync = [];
    }
}
