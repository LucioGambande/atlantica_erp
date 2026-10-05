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

    public function particularPrefix(): string
    {
        return (string) config('invoices.particular_number_prefix', 'PARTICULAR');
    }

    public function padding(): int
    {
        return max(1, (int) config('invoices.number_padding', 5));
    }

    public function patternForYear(?string $basePrefix = null, ?int $year = null): string
    {
        $basePrefix ??= $this->prefix();
        $year ??= (int) now()->format('Y');

        return $basePrefix.$year.'-';
    }

    public function preview(?string $basePrefix = null, ?int $year = null): string
    {
        return $this->nextSequenceCandidate($basePrefix, $year);
    }

    public function next(?string $basePrefix = null, ?int $year = null): string
    {
        $basePrefix ??= $this->prefix();

        return DB::transaction(function () use ($basePrefix, $year): string {
            $candidate = $this->nextSequenceCandidate($basePrefix, $year);

            while (Invoice::query()->where('invoice_number', $candidate)->lockForUpdate()->exists()) {
                $candidate = $this->incrementCandidate($candidate, $basePrefix);
            }

            return $candidate;
        });
    }

    protected function nextSequenceCandidate(?string $basePrefix = null, ?int $year = null): string
    {
        $prefix = $this->patternForYear($basePrefix, $year);

        $maxSequence = Invoice::query()
            ->where('invoice_number', 'like', $prefix.'%')
            ->pluck('invoice_number')
            ->map(fn (string $number): ?int => $this->extractSequence($number, $prefix))
            ->filter()
            ->max() ?? 0;

        return $prefix.$this->formatSequence($maxSequence + 1);
    }

    protected function incrementCandidate(string $invoiceNumber, ?string $basePrefix = null): string
    {
        $year = (int) now()->format('Y');
        $prefix = $this->patternForYear($basePrefix, $year);

        if (! str_starts_with($invoiceNumber, $prefix)) {
            return $this->nextSequenceCandidate($basePrefix, $year);
        }

        $sequence = $this->extractSequence($invoiceNumber, $prefix) ?? 0;

        return $prefix.$this->formatSequence($sequence + 1);
    }

    public function parse(string $invoiceNumber): ?array
    {
        foreach ($this->knownPrefixesForNumber($invoiceNumber) as [$basePrefix, $prefix]) {
            $sequence = $this->extractSequence($invoiceNumber, $prefix);

            if ($sequence === null) {
                continue;
            }

            $year = (int) substr($prefix, strlen($basePrefix), 4);

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
     * @return array<int, array{0: string, 1: string}> pares [prefijo base, prefijo+año]
     */
    protected function knownPrefixesForNumber(string $invoiceNumber): array
    {
        $matches = [];

        foreach ([$this->prefix(), $this->particularPrefix()] as $basePrefix) {
            if (preg_match('/^('.preg_quote($basePrefix, '/').'\d{4}-)/', $invoiceNumber, $m) === 1) {
                $matches[] = [$basePrefix, $m[1]];
            }
        }

        return $matches;
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
