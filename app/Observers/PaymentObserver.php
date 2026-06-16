<?php

namespace App\Observers;

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

    public function deleted(Payment $payment): void
    {
        $payment->loadMissing('customer');

        $this->accountStatementService->registerPaymentReversal($payment);
    }
}
