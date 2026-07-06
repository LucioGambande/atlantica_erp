<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\AccountStatementService;

class PaymentObserver
{
    public function __construct(
        protected AccountStatementService $accountStatementService,
    ) {
    }

    public function created(Payment $payment): void
    {
        $payment->loadMissing('customer', 'invoice', 'paymentMethod');

        $this->accountStatementService->registerPayment($payment);
    }

    public function updated(Payment $payment): void
    {
        if (! $payment->wasChanged(['amount', 'paid_at', 'customer_id', 'invoice_id', 'payment_method_id'])) {
            return;
        }

        $payment->loadMissing('customer', 'invoice', 'paymentMethod');

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

    public function deleted(Payment $payment): void
    {
        $payment->loadMissing('customer');

        $this->accountStatementService->registerPaymentReversal($payment);
    }
}
