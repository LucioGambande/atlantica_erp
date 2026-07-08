<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Support\InvoiceLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountStatementService
{
    public function registerInvoice(Invoice $invoice): ?LedgerEntry
    {
        if (! in_array($invoice->status, ['issued', 'paid'], true)) {
            return null;
        }

        if ($invoice->isCancelled() && ! $invoice->isCreditNote()) {
            return null;
        }

        $type = $invoice->isCreditNote()
            ? LedgerEntry::TYPE_CREDIT_NOTE
            : LedgerEntry::TYPE_INVOICE;

        if ($this->hasEntryFor($invoice, $type)) {
            return $this->findEntryFor($invoice, $type);
        }

        $amount = abs((float) $invoice->total_amount);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($invoice, $type, $amount): LedgerEntry {
            $debit = $invoice->isCreditNote() ? 0 : $amount;
            $credit = $invoice->isCreditNote() ? $amount : 0;

            $entry = $this->createEntry(
                customer: $invoice->customer,
                type: $type,
                reference: $invoice,
                date: $this->invoiceDate($invoice),
                description: $this->invoiceDescription($invoice),
                debit: $debit,
                credit: $credit,
            );

            $this->recalculateRunningBalances($invoice->customer);

            return $entry;
        });
    }

    public function registerInvoiceReversal(Invoice $invoice): ?LedgerEntry
    {
        $type = $invoice->isCreditNote()
            ? LedgerEntry::TYPE_CREDIT_NOTE
            : LedgerEntry::TYPE_INVOICE;

        $original = $this->findEntryFor($invoice, $type);

        if ($original === null) {
            return null;
        }

        return $this->registerAdjustment(
            customer: $invoice->customer,
            reference: $invoice,
            description: 'Anulación: '.$this->invoiceDescription($invoice),
            debit: (float) $original->credit,
            credit: (float) $original->debit,
            date: Carbon::today(),
        );
    }

    public function syncPayment(Payment $payment): void
    {
        $payment->loadMissing('customer', 'invoice', 'paymentMethod');

        $entry = $this->findEntryFor($payment, LedgerEntry::TYPE_PAYMENT);

        if ($entry === null) {
            $this->registerPayment($payment);

            return;
        }

        DB::transaction(function () use ($payment, $entry): void {
            $amount = round((float) $payment->amount, 2);

            $entry->update([
                'customer_id' => $payment->customer_id,
                'date' => Carbon::parse($payment->paid_at)->toDateString(),
                'description' => $this->paymentDescription($payment),
                'debit' => 0,
                'credit' => $amount,
            ]);

            $this->recalculateRunningBalances($payment->customer);
        });
    }

    public function registerPayment(Payment $payment): ?LedgerEntry
    {
        if ($this->hasEntryFor($payment, LedgerEntry::TYPE_PAYMENT)) {
            return $this->findEntryFor($payment, LedgerEntry::TYPE_PAYMENT);
        }

        $amount = round((float) $payment->amount, 2);

        if ($amount <= 0) {
            return null;
        }

        return DB::transaction(function () use ($payment, $amount): LedgerEntry {
            $entry = $this->createEntry(
                customer: $payment->customer,
                type: LedgerEntry::TYPE_PAYMENT,
                reference: $payment,
                date: Carbon::parse($payment->paid_at)->toDateString(),
                description: $this->paymentDescription($payment),
                debit: 0,
                credit: $amount,
            );

            $this->recalculateRunningBalances($payment->customer);

            return $entry;
        });
    }

    public function registerPaymentReversal(Payment $payment): ?LedgerEntry
    {
        return $this->registerAdjustment(
            customer: $payment->customer,
            reference: $payment,
            description: 'Reversión de pago #'.$payment->id,
            debit: round((float) $payment->amount, 2),
            credit: 0,
            date: Carbon::today(),
        );
    }

    public function rebuildLedger(?Customer $customer = null): void
    {
        $customers = $customer !== null
            ? collect([$customer])
            : Customer::query()->get();

        foreach ($customers as $client) {
            DB::transaction(function () use ($client): void {
                $client->ledgerEntries()->delete();

                $events = collect();

                $invoices = $client->invoices()
                    ->whereIn('status', ['issued', 'paid'])
                    ->orderBy('issued_at')
                    ->orderBy('id')
                    ->get();

                foreach ($invoices as $invoice) {
                    $amount = abs((float) $invoice->total_amount);

                    if ($amount <= 0) {
                        continue;
                    }

                    $events->push([
                        'customer_id' => $client->id,
                        'type' => $invoice->isCreditNote()
                            ? LedgerEntry::TYPE_CREDIT_NOTE
                            : LedgerEntry::TYPE_INVOICE,
                        'reference_type' => $invoice->getMorphClass(),
                        'reference_id' => $invoice->id,
                        'date' => $this->invoiceDate($invoice),
                        'description' => $this->invoiceDescription($invoice),
                        'debit' => $invoice->isCreditNote() ? 0 : $amount,
                        'credit' => $invoice->isCreditNote() ? $amount : 0,
                        'sort_at' => $this->invoiceDate($invoice).' '.$invoice->id,
                    ]);
                }

                $payments = $client->payments()
                    ->orderBy('paid_at')
                    ->orderBy('id')
                    ->get();

                foreach ($payments as $payment) {
                    $amount = round((float) $payment->amount, 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    $events->push([
                        'customer_id' => $client->id,
                        'type' => LedgerEntry::TYPE_PAYMENT,
                        'reference_type' => $payment->getMorphClass(),
                        'reference_id' => $payment->id,
                        'date' => Carbon::parse($payment->paid_at)->toDateString(),
                        'description' => $this->paymentDescription($payment),
                        'debit' => 0,
                        'credit' => $amount,
                        'sort_at' => Carbon::parse($payment->paid_at)->toDateTimeString().' '.$payment->id,
                    ]);
                }

                $runningBalance = 0.0;

                $events
                    ->sortBy('sort_at')
                    ->values()
                    ->each(function (array $event) use (&$runningBalance): void {
                        $runningBalance = round(
                            $runningBalance + (float) $event['debit'] - (float) $event['credit'],
                            2,
                        );

                        LedgerEntry::create([
                            'customer_id' => $event['customer_id'],
                            'type' => $event['type'],
                            'reference_type' => $event['reference_type'],
                            'reference_id' => $event['reference_id'],
                            'date' => $event['date'],
                            'description' => $event['description'],
                            'debit' => $event['debit'],
                            'credit' => $event['credit'],
                            'running_balance' => $runningBalance,
                        ]);
                    });

                $client->update(['balance' => $runningBalance]);
            });
        }
    }

    /**
     * @return array{
     *     balance_due: float,
     *     total_invoiced: float,
     *     total_paid: float,
     *     overdue_amount: float,
     *     entries: Collection<int, LedgerEntry>
     * }
     */
    public function getStatement(
        Customer $customer,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?string $typeFilter = null,
        bool $excludeSettledInvoices = false,
    ): array {
        $query = $customer->ledgerEntries()
            ->with('reference')
            ->orderBy('date')
            ->orderBy('id');

        if ($from !== null) {
            $query->whereDate('date', '>=', $from);
        }

        if ($to !== null) {
            $query->whereDate('date', '<=', $to);
        }

        if ($typeFilter === 'invoice') {
            $query->whereIn('type', [LedgerEntry::TYPE_INVOICE, LedgerEntry::TYPE_CREDIT_NOTE]);
        } elseif ($typeFilter === 'payment') {
            $query->where('type', LedgerEntry::TYPE_PAYMENT);
        }

        $this->applySettledInvoiceExclusion($query, $excludeSettledInvoices);

        $entries = $query->get();

        if ($excludeSettledInvoices) {
            $entries = $this->recalculateRunningBalancesForEntries($entries);
        }

        $totalInvoiced = round((float) $customer->ledgerEntries()
            ->whereIn('type', [LedgerEntry::TYPE_INVOICE])
            ->sum('debit'), 2);

        $totalCreditNotes = round((float) $customer->ledgerEntries()
            ->where('type', LedgerEntry::TYPE_CREDIT_NOTE)
            ->sum('credit'), 2);

        $totalPaid = round((float) $customer->ledgerEntries()
            ->where('type', LedgerEntry::TYPE_PAYMENT)
            ->sum('credit'), 2);

        $overdueAmount = round((float) $customer->invoices()
            ->where('document_type', 'invoice')
            ->where('status', 'issued')
            ->whereNull('cancelled_at')
            ->get()
            ->sum(fn (Invoice $invoice): float => $invoice->remainingAmount()), 2);

        return [
            'balance_due' => round((float) $customer->balance, 2),
            'total_invoiced' => round($totalInvoiced - $totalCreditNotes, 2),
            'total_paid' => $totalPaid,
            'overdue_amount' => $overdueAmount,
            'entries' => $entries,
            'total_debit' => round((float) $entries->sum('debit'), 2),
            'total_credit' => round((float) $entries->sum('credit'), 2),
            'final_balance' => round((float) ($entries->last()?->running_balance ?? $customer->balance), 2),
            'exclude_settled_invoices' => $excludeSettledInvoices,
        ];
    }

    /**
     * @param  Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @return Builder|\Illuminate\Database\Eloquent\Relations\Relation
     */
    public function applySettledInvoiceExclusion($query, bool $exclude)
    {
        if (! $exclude) {
            return $query;
        }

        $invoiceMorph = (new Invoice)->getMorphClass();

        return $query->whereNot(function (Builder $q) use ($invoiceMorph): void {
            $q->where('type', LedgerEntry::TYPE_INVOICE)
                ->where('reference_type', $invoiceMorph)
                ->whereIn('reference_id', Invoice::query()->settled()->select('id'));
        });
    }

    /**
     * @param  Collection<int, LedgerEntry>  $entries
     * @return Collection<int, LedgerEntry>
     */
    protected function recalculateRunningBalancesForEntries(Collection $entries): Collection
    {
        $runningBalance = 0.0;

        return $entries->map(function (LedgerEntry $entry) use (&$runningBalance): LedgerEntry {
            $runningBalance = round(
                $runningBalance + (float) $entry->debit - (float) $entry->credit,
                2,
            );
            $entry->setAttribute('running_balance', $runningBalance);

            return $entry;
        });
    }

    public function recalculateRunningBalances(Customer $customer): void
    {
        $runningBalance = 0.0;

        $customer->ledgerEntries()
            ->orderBy('date')
            ->orderBy('id')
            ->get()
            ->each(function (LedgerEntry $entry) use (&$runningBalance): void {
                $runningBalance = round(
                    $runningBalance + (float) $entry->debit - (float) $entry->credit,
                    2,
                );

                $entry->update(['running_balance' => $runningBalance]);
            });

        $customer->update(['balance' => $runningBalance]);
    }

    protected function registerAdjustment(
        Customer $customer,
        Model $reference,
        string $description,
        float $debit,
        float $credit,
        Carbon|string $date,
    ): ?LedgerEntry {
        if ($debit <= 0 && $credit <= 0) {
            return null;
        }

        return DB::transaction(function () use ($customer, $reference, $description, $debit, $credit, $date): LedgerEntry {
            $entry = $this->createEntry(
                customer: $customer,
                type: LedgerEntry::TYPE_ADJUSTMENT,
                reference: $reference,
                date: $date instanceof Carbon ? $date->toDateString() : $date,
                description: $description,
                debit: $debit,
                credit: $credit,
            );

            $this->recalculateRunningBalances($customer);

            return $entry;
        });
    }

    protected function createEntry(
        Customer $customer,
        string $type,
        Model $reference,
        Carbon|string $date,
        string $description,
        float $debit,
        float $credit,
    ): LedgerEntry {
        $customer = Customer::query()->lockForUpdate()->findOrFail($customer->id);

        $lastBalance = round((float) $customer->ledgerEntries()
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('running_balance') ?? 0, 2);

        $runningBalance = round($lastBalance + $debit - $credit, 2);

        return LedgerEntry::create([
            'customer_id' => $customer->id,
            'type' => $type,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'date' => $date instanceof Carbon ? $date->toDateString() : $date,
            'description' => $description,
            'debit' => $debit,
            'credit' => $credit,
            'running_balance' => $runningBalance,
        ]);
    }

    protected function hasEntryFor(Model $reference, string $type): bool
    {
        return LedgerEntry::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->id)
            ->where('type', $type)
            ->exists();
    }

    protected function findEntryFor(Model $reference, string $type): ?LedgerEntry
    {
        return LedgerEntry::query()
            ->where('reference_type', $reference->getMorphClass())
            ->where('reference_id', $reference->id)
            ->where('type', $type)
            ->first();
    }

    protected function invoiceDate(Invoice $invoice): string
    {
        return ($invoice->issued_at ?? $invoice->created_at)->toDateString();
    }

    protected function invoiceDescription(Invoice $invoice): string
    {
        $prefix = $invoice->isCreditNote() ? 'Nota de crédito' : 'Factura';

        return $prefix.' '.InvoiceLabel::numberAndDate($invoice);
    }

    protected function paymentDescription(Payment $payment): string
    {
        $payment->loadMissing('allocations.invoice', 'paymentMethod', 'invoice');

        if ($payment->allocations->isNotEmpty()) {
            $parts = $payment->allocations
                ->map(function ($allocation): string {
                    $invoice = $allocation->invoice;

                    if ($invoice === null) {
                        return 'Factura: '.number_format((float) $allocation->amount, 2, ',', '.').' €';
                    }

                    return InvoiceLabel::withAllocatedAmount($invoice, (float) $allocation->amount);
                })
                ->all();

            $method = $payment->paymentMethod?->name ?? 'Pago';

            return $method.' — '.implode(' · ', $parts);
        }

        $method = $payment->paymentMethod?->name ?? 'Pago';
        $invoice = $payment->invoice;

        if ($invoice !== null) {
            return $method.' — Factura '.InvoiceLabel::numberAndDate($invoice);
        }

        return $method.' #'.$payment->id;
    }
}
