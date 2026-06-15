<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\DB;

class InvoiceNumberGenerator
{
    public function prefix(): string
    {
        return (string) config('invoices.number_prefix', 'HORECA');
    }

    public function padding(): int
    {
        return max(1, (int) config('invoices.number_padding', 5));
    }

    public function patternForYear(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return $this->prefix().$year.'-';
    }

    public function preview(?int $year = null): string
    {
        return $this->nextSequenceCandidate($year);
    }

    public function next(?int $year = null): string
    {
        return DB::transaction(function () use ($year): string {
            $candidate = $this->nextSequenceCandidate($year);

            while (Invoice::query()->where('invoice_number', $candidate)->lockForUpdate()->exists()) {
                $candidate = $this->incrementCandidate($candidate);
            }

            return $candidate;
        });
    }

    protected function nextSequenceCandidate(?int $year = null): string
    {
        $prefix = $this->patternForYear($year);

        $maxSequence = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->pluck('invoice_number')
            ->map(fn (string $number): ?int => $this->extractSequence($number, $prefix))
            ->filter()
            ->max() ?? 0;

        return $prefix.$this->formatSequence($maxSequence + 1);
    }

    protected function incrementCandidate(string $invoiceNumber): string
    {
        $year = (int) now()->format('Y');
        $prefix = $this->patternForYear($year);

        if (! str_starts_with($invoiceNumber, $prefix)) {
            return $this->nextSequenceCandidate($year);
        }

        $sequence = $this->extractSequence($invoiceNumber, $prefix) ?? 0;

        return $prefix.$this->formatSequence($sequence + 1);
    }

    protected function extractSequence(string $invoiceNumber, string $prefix): ?int
    {
        if (! str_starts_with($invoiceNumber, $prefix)) {
            return null;
        }

        $suffix = substr($invoiceNumber, strlen($prefix));

        if ($suffix === '' || ! ctype_digit($suffix)) {
            return null;
        }

        return (int) $suffix;
    }

    protected function formatSequence(int $sequence): string
    {
        return str_pad((string) $sequence, $this->padding(), '0', STR_PAD_LEFT);
    }
}
