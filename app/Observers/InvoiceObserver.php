<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\AccountStatementService;

class InvoiceObserver
{
    public function __construct(
        protected AccountStatementService $accountStatementService,
    ) {
    }

    public function created(Invoice $invoice): void
    {
        if ($invoice->status === 'issued') {
            $this->accountStatementService->registerInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice): void
    {
        if (! $invoice->wasChanged('status')) {
            return;
        }

        $previousStatus = $invoice->getOriginal('status');

        if ($invoice->status === 'issued') {
            $this->accountStatementService->registerInvoice($invoice);
        }

        if ($previousStatus === 'issued' && $invoice->status === 'draft') {
            $this->accountStatementService->registerInvoiceReversal($invoice);
        }
    }
}
