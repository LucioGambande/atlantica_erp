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
        if (in_array($invoice->status, ['issued', 'paid'], true)) {
            $this->accountStatementService->registerInvoice($invoice);
        }
    }

    public function updated(Invoice $invoice): void
    {
        // El asiento puede crearse antes de las líneas / recálculo con IVA.
        // Hay que re-sincronizar cuando cambian importe, fecha o estado.
        if (! $invoice->wasChanged(['status', 'total_amount', 'issued_at', 'cancelled_at', 'customer_id'])) {
            return;
        }

        $previousStatus = $invoice->getOriginal('status');

        if ($invoice->isCancelled() && ! $invoice->isCreditNote()) {
            $this->accountStatementService->registerInvoiceReversal($invoice);

            return;
        }

        if (in_array($invoice->status, ['issued', 'paid'], true)) {
            $this->accountStatementService->registerInvoice($invoice);
        }

        if ($previousStatus === 'issued' && $invoice->status === 'draft') {
            $this->accountStatementService->registerInvoiceReversal($invoice);
        }
    }
}
