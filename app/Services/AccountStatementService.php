<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
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

        $entries = $query->get();

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
        ];
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
        if ($invoice->isCreditNote()) {
            return 'Nota de crédito '.$invoice->invoice_number;
        }

        return 'Factura '.$invoice->invoice_number;
    }

    protected function paymentDescription(Payment $payment): string
    {
        $method = $payment->paymentMethod?->name ?? 'Pago';
        $invoiceRef = $payment->invoice?->invoice_number;

        if ($invoiceRef !== null) {
            return $method.' — Factura '.$invoiceRef;
        }

        return $method.' #'.$payment->id;
    }
}
