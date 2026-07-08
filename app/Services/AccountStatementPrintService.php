<?php

namespace App\Services;

use App\Models\Customer;
use Illuminate\Support\Carbon;

class AccountStatementPrintService
{
    public function __construct(
        protected AccountStatementService $accountStatementService,
        protected InvoicePrintService $invoicePrintService,
    ) {
    }

    public function logoBase64(): ?string
    {
        return $this->invoicePrintService->logoBase64();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrintData(
        Customer $customer,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $entryType = null,
        bool $excludeSettledInvoices = false,
    ): array {
        $typeFilter = match ($entryType) {
            'invoice', 'payment' => $entryType,
            default => null,
        };

        $summary = $this->accountStatementService->getStatement(
            $customer,
            $from,
            $to,
            $typeFilter,
            $excludeSettledInvoices,
        );

        return [
            'title' => 'Cuenta corriente',
            'customer' => $customer,
            'summary' => $summary,
            'entries' => $summary['entries'],
            'period_label' => $this->periodLabel($from, $to),
            'type_label' => $this->typeLabel($entryType, $excludeSettledInvoices),
            'generated_at' => now(),
        ];
    }

    public function pdfFilename(Customer $customer): string
    {
        $slug = str_replace(['/', '\\', ' '], '-', $customer->name);

        return 'cuenta-corriente-'.$slug.'.pdf';
    }

    protected function periodLabel(?Carbon $from, ?Carbon $to): string
    {
        if ($from === null && $to === null) {
            return 'Todo el historial';
        }

        if ($from !== null && $to !== null) {
            return $from->format('d/m/Y').' — '.$to->format('d/m/Y');
        }

        if ($from !== null) {
            return 'Desde '.$from->format('d/m/Y');
        }

        return 'Hasta '.$to?->format('d/m/Y');
    }

    protected function typeLabel(?string $entryType, bool $excludeSettledInvoices = false): string
    {
        $base = match ($entryType) {
            'invoice' => 'Solo facturas',
            'payment' => 'Solo pagos',
            default => 'Todos los movimientos',
        };

        if ($excludeSettledInvoices) {
            return $base.' (sin facturas liquidadas)';
        }

        return $base;
    }
}
