<?php

namespace App\Services;

use App\Models\Invoice;
use App\Support\LineItemTotals;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class InvoicePrintService
{
    public function __construct(
        protected InvoiceNumberGenerator $numberGenerator,
    ) {
    }

    public function logoBase64(): ?string
    {
        $relativePath = (string) config('invoices.logo_path', 'images/brand/atlantica-terranova-logo.png');
        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return base64_encode($contents);
    }

    /**
     * @param  array<int, array<string, mixed>>  $documents
     */
    public function pdfFilename(array $documents): string
    {
        if (count($documents) === 1) {
            return str_replace('/', '-', (string) $documents[0]['invoice_number']).'.pdf';
        }

        $first = str_replace('/', '-', (string) $documents[0]['invoice_number']);
        $last = str_replace('/', '-', (string) $documents[array_key_last($documents)]['invoice_number']);

        return "facturas-{$first}-{$last}.pdf";
    }

    public function printableStatuses(): array
    {
        return ['issued', 'paid'];
    }

    public function findForPrint(int $invoiceId): Invoice
    {
        $invoice = Invoice::query()
            ->with(['customer', 'invoiceItems.product'])
            ->whereIn('status', $this->printableStatuses())
            ->findOrFail($invoiceId);

        return $invoice;
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function findRangeForPrint(string $fromNumber, string $toNumber): Collection
    {
        $from = trim($fromNumber);
        $to = trim($toNumber);

        if ($from === '' || $to === '') {
            throw new InvalidArgumentException('Indicá el número inicial y final del rango.');
        }

        if ($this->numberGenerator->compareNumbers($from, $to) > 0) {
            throw new InvalidArgumentException('El número inicial no puede ser mayor que el final.');
        }

        return Invoice::query()
            ->with(['customer', 'invoiceItems.product'])
            ->whereIn('status', $this->printableStatuses())
            ->where('document_type', 'invoice')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $this->numberGenerator->isInRange(
                $invoice->invoice_number,
                $from,
                $to,
            ))
            ->sortBy(fn (Invoice $invoice): array => $this->numberGenerator->sortKey($invoice->invoice_number))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrintData(Invoice $invoice): array
    {
        $issuedAt = Carbon::parse($invoice->issued_at ?? $invoice->created_at);
        $paymentTermsDays = (int) config('invoices.payment_terms_days', 21);
        $vatRate = (float) config('invoices.default_vat_rate', 0.21);
        $issuer = config('invoices.issuer', []);
        $customer = $invoice->customer;
        $isCreditNote = $invoice->isCreditNote();
        $sign = $isCreditNote ? -1 : 1;

        $lines = $invoice->invoiceItems->map(function ($item) use ($vatRate, $sign): array {
            $baseTotal = LineItemTotals::discountedLineTotal(
                abs((float) $item->unit_price),
                abs((int) $item->quantity),
                abs((float) $item->discount_percent),
            );
            $totalWithVat = round($baseTotal * (1 + $vatRate), 4);

            return [
                'description' => $item->description ?: ($item->product?->name ?? 'Línea'),
                'quantity' => abs((int) $item->quantity),
                'unit_price' => round(abs((float) $item->unit_price), 2),
                'vat_rate' => $vatRate,
                'discount_percent' => round(abs((float) $item->discount_percent), 2),
                'line_total' => round($sign * $totalWithVat, 2),
            ];
        });

        $subtotal = round($lines->sum(fn (array $line): float => $line['line_total'] / (1 + $vatRate)), 2);
        $total = round($lines->sum(fn (array $line): float => $line['line_total']), 2);

        return [
            'invoice' => $invoice,
            'title' => $isCreditNote ? 'NOTA DE CRÉDITO '.$invoice->invoice_number : 'FACTURA '.$invoice->invoice_number,
            'invoice_number' => $invoice->invoice_number,
            'issued_at' => $issuedAt,
            'due_at' => $issuedAt->copy()->addDays($paymentTermsDays),
            'issuer' => $issuer,
            'customer' => [
                'name' => $customer?->name ?? '—',
                'address' => $customer?->address,
                'tax_id' => $customer?->tax_id,
                'postal_code' => $customer?->postal_code,
                'city' => $customer?->city,
                'email' => $customer?->email,
            ],
            'lines' => $lines,
            'subtotal' => $subtotal,
            'total' => $total,
            'vat_rate' => $vatRate,
            'iban' => $issuer['iban'] ?? null,
            'is_credit_note' => $isCreditNote,
        ];
    }
}
