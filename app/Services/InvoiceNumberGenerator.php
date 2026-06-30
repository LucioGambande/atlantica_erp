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

    public function parse(string $invoiceNumber): ?array
    {
        foreach ($this->knownPrefixesForNumber($invoiceNumber) as $prefix) {
            $sequence = $this->extractSequence($invoiceNumber, $prefix);

            if ($sequence === null) {
                continue;
            }

            $year = (int) substr($prefix, strlen($this->prefix()), 4);

            return [
                'prefix' => $prefix,
                'sequence' => $sequence,
                'year' => $year,
            ];
        }

        return null;
    }

    public function extractSequence(string $invoiceNumber, string $prefix): ?int
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

    /**
     * @return array<int, string>
     */
    protected function knownPrefixesForNumber(string $invoiceNumber): array
    {
        if (preg_match('/^('.preg_quote($this->prefix(), '/').'\d{4}-)/', $invoiceNumber, $matches) === 1) {
            return [$matches[1]];
        }

        return [];
    }

    public function compareNumbers(string $left, string $right): int
    {
        $leftKey = $this->sortKey($left);
        $rightKey = $this->sortKey($right);

        return $leftKey <=> $rightKey;
    }

    public function isInRange(string $invoiceNumber, string $from, string $to): bool
    {
        return $this->compareNumbers($invoiceNumber, $from) >= 0
            && $this->compareNumbers($invoiceNumber, $to) <= 0;
    }

    /**
     * @return array{0: int, 1: int}
     */
    public function sortKey(string $invoiceNumber): array
    {
        $parsed = $this->parse($invoiceNumber);

        if ($parsed === null) {
            return [PHP_INT_MAX, PHP_INT_MAX];
        }

        return [$parsed['year'], $parsed['sequence']];
    }
}
