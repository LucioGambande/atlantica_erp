<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class InvoiceSequenceValidator
{
    public function __construct(
        protected InvoiceNumberGenerator $numberGenerator,
    ) {
    }

    public function validate(string $invoiceNumber, Carbon|string|null $issuedAt, ?int $exceptInvoiceId = null): void
    {
        $parsed = $this->numberGenerator->parse($invoiceNumber);

        if ($parsed === null) {
            return;
        }

        if ($issuedAt === null) {
            throw new InvalidArgumentException('La fecha de emisión es obligatoria para facturas con numeración correlativa.');
        }

        $issuedAt = Carbon::parse($issuedAt);
        $neighbors = $this->neighbors($parsed, $exceptInvoiceId);

        if ($neighbors['previous'] !== null) {
            $previousDate = $this->resolveIssuedAt($neighbors['previous']);

            if ($previousDate !== null && $issuedAt->lt($previousDate)) {
                throw new InvalidArgumentException(sprintf(
                    'La factura %s no puede tener fecha anterior a %s (%s).',
                    $invoiceNumber,
                    $neighbors['previous']->invoice_number,
                    $previousDate->format('d/m/Y H:i'),
                ));
            }
        }

        if ($neighbors['next'] !== null) {
            $nextDate = $this->resolveIssuedAt($neighbors['next']);

            if ($nextDate !== null && $issuedAt->gt($nextDate)) {
                throw new InvalidArgumentException(sprintf(
                    'La factura %s no puede tener fecha posterior a %s (%s).',
                    $invoiceNumber,
                    $neighbors['next']->invoice_number,
                    $nextDate->format('d/m/Y H:i'),
                ));
            }
        }
    }

    public function minimumIssuedAt(string $invoiceNumber, ?int $exceptInvoiceId = null): ?Carbon
    {
        $parsed = $this->numberGenerator->parse($invoiceNumber);

        if ($parsed === null) {
            return null;
        }

        $previous = $this->neighbors($parsed, $exceptInvoiceId)['previous'];

        return $previous !== null ? $this->resolveIssuedAt($previous) : null;
    }

    public function minimumIssuedAtForNextInYear(?int $year = null): ?Carbon
    {
        $year ??= (int) now()->format('Y');
        $prefix = $this->numberGenerator->patternForYear($year);

        $latest = $this->invoicesWithParsedSequence($prefix, null)
            ->sortByDesc('sequence')
            ->first();

        if ($latest === null) {
            return null;
        }

        return $this->resolveIssuedAt($latest['invoice']);
    }

    /**
     * @return array{previous: ?Invoice, next: ?Invoice}
     */
    protected function neighbors(array $parsed, ?int $exceptInvoiceId): array
    {
        $invoices = $this->invoicesWithParsedSequence($parsed['prefix'], $exceptInvoiceId);

        $previous = $invoices
            ->filter(fn (array $row): bool => $row['sequence'] < $parsed['sequence'])
            ->sortByDesc('sequence')
            ->first();

        $next = $invoices
            ->filter(fn (array $row): bool => $row['sequence'] > $parsed['sequence'])
            ->sortBy('sequence')
            ->first();

        return [
            'previous' => $previous['invoice'] ?? null,
            'next' => $next['invoice'] ?? null,
        ];
    }

    protected function invoicesWithParsedSequence(string $prefix, ?int $exceptInvoiceId): \Illuminate\Support\Collection
    {
        return Invoice::query()
            ->when($exceptInvoiceId !== null, fn ($query) => $query->whereKeyNot($exceptInvoiceId))
            ->where('invoice_number', 'like', $prefix.'%')
            ->get()
            ->map(function (Invoice $invoice) use ($prefix): ?array {
                $sequence = $this->numberGenerator->extractSequence($invoice->invoice_number, $prefix);

                if ($sequence === null) {
                    return null;
                }

                return [
                    'invoice' => $invoice,
                    'sequence' => $sequence,
                ];
            })
            ->filter()
            ->values();
    }

    protected function resolveIssuedAt(Invoice $invoice): ?Carbon
    {
        $issuedAt = $invoice->issued_at ?? $invoice->created_at;

        return $issuedAt !== null ? Carbon::parse($issuedAt) : null;
    }
}
