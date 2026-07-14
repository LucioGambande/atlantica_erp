<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function __construct(
        protected PaymentDetailService $paymentDetailService,
    ) {
    }

    public function registerInvoicePayment(
        Invoice $invoice,
        int $paymentMethodId,
        array $detail = [],
        ?Carbon $paidAt = null,
        ?float $amount = null,
    ): Payment {
        return DB::transaction(function () use ($invoice, $paymentMethodId, $detail, $paidAt, $amount): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'draft') {
                throw new InvalidArgumentException('No se puede registrar un pago en una factura en borrador.');
            }

            if ($invoice->isCancelled()) {
                throw new InvalidArgumentException('No se puede registrar un pago en una factura cancelada.');
            }

            $remaining = $invoice->remainingAmount();

            if ($remaining <= 0) {
                throw new InvalidArgumentException('La factura no tiene saldo pendiente.');
            }

            $paymentAmount = round($amount ?? $remaining, 2);

            if ($paymentAmount <= 0) {
                throw new InvalidArgumentException('El importe del pago debe ser mayor que cero.');
            }

            if ($paymentAmount > $remaining) {
                throw new InvalidArgumentException('El importe no puede superar el saldo pendiente de la factura.');
            }

            return $this->registerPayment([
                'customer_id' => $invoice->customer_id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $paymentAmount,
                'detail' => $detail,
                'paid_at' => $paidAt ?? now(),
                'allocations' => [
                    [
                        'invoice_id' => $invoice->id,
                        'amount' => $paymentAmount,
                    ],
                ],
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function registerPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $amount = round((float) ($data['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException('El importe del pago debe ser mayor que cero.');
            }

            $paymentMethod = PaymentMethod::query()
                ->active()
                ->find($data['payment_method_id'] ?? null);

            if ($paymentMethod === null) {
                throw new InvalidArgumentException('La forma de pago seleccionada no es válida.');
            }

            /** @var list<array{invoice_id?: int|null, amount?: float|int|string}> $allocations */
            $allocations = is_array($data['allocations'] ?? null) ? $data['allocations'] : [];

            if ($allocations === [] && filled($data['invoice_id'] ?? null)) {
                $allocations = [[
                    'invoice_id' => (int) $data['invoice_id'],
                    'amount' => $amount,
                ]];
            }

            $this->validateAllocations(
                customerId: (int) $data['customer_id'],
                paymentAmount: $amount,
                allocations: $allocations,
            );

            $detail = $this->paymentDetailService->createForMethod(
                $paymentMethod,
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
            );

            $primaryInvoiceId = $this->resolvePrimaryInvoiceId($allocations);

            $payment = Payment::create([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $primaryInvoiceId,
                'payment_method_id' => $paymentMethod->id,
                'detail_type' => $paymentMethod->detail_type,
                'detail_id' => $detail->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'],
            ]);

            $this->persistAllocations($payment, $allocations);
            $this->syncInvoicesFromAllocations($this->invoiceIdsFromAllocations($allocations));

            return $payment->load('allocations.invoice', 'invoice', 'customer', 'paymentMethod', 'detail');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePayment(Payment $payment, array $data): Payment
    {
        return DB::transaction(function () use ($payment, $data): Payment {
            $amount = round((float) ($data['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException('El importe del pago debe ser mayor que cero.');
            }

            $paymentMethod = PaymentMethod::query()
                ->active()
                ->find($data['payment_method_id'] ?? null);

            if ($paymentMethod === null) {
                throw new InvalidArgumentException('La forma de pago seleccionada no es válida.');
            }

            $previousInvoiceIds = $payment->allocations()
                ->pluck('invoice_id')
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->all();

            /** @var list<array{invoice_id?: int|null, amount?: float|int|string}> $allocations */
            $allocations = is_array($data['allocations'] ?? null) ? $data['allocations'] : [];

            if ($allocations === [] && filled($data['invoice_id'] ?? null)) {
                $allocations = [[
                    'invoice_id' => (int) $data['invoice_id'],
                    'amount' => $amount,
                ]];
            }

            $this->validateAllocations(
                customerId: (int) $data['customer_id'],
                paymentAmount: $amount,
                allocations: $allocations,
                paymentId: $payment->id,
            );

            $this->paymentDetailService->updateForPayment(
                $payment,
                $paymentMethod,
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
            );

            $payment->update([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $this->resolvePrimaryInvoiceId($allocations),
                'payment_method_id' => $paymentMethod->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'],
            ]);

            $payment->allocations()->delete();
            $this->persistAllocations($payment, $allocations);

            $invoiceIds = array_values(array_unique(array_merge(
                $previousInvoiceIds,
                $this->invoiceIdsFromAllocations($allocations),
            )));

            $this->syncInvoicesFromAllocations($invoiceIds);

            return $payment->fresh(['allocations.invoice', 'invoice', 'customer', 'paymentMethod', 'detail']);
        });
    }

    public function syncInvoicePaymentStatus(?int $invoiceId): void
    {
        if ($invoiceId === null) {
            return;
        }

        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null || $invoice->status === 'draft' || $invoice->isCancelled()) {
            return;
        }

        $invoice->update([
            'status' => $invoice->remainingAmount() <= 0 ? 'paid' : 'issued',
        ]);
    }

    /**
     * @param  list<array{invoice_id?: int|null, amount?: float|int|string}>  $allocations
     */
    protected function validateAllocations(
        int $customerId,
        float $paymentAmount,
        array $allocations,
        ?int $paymentId = null,
    ): void {
        $allocatedTotal = 0.0;

        foreach ($allocations as $index => $allocation) {
            $invoiceId = isset($allocation['invoice_id']) ? (int) $allocation['invoice_id'] : null;
            $lineAmount = round((float) ($allocation['amount'] ?? 0), 2);

            if ($lineAmount <= 0) {
                throw new InvalidArgumentException('Cada imputación debe tener un importe mayor que cero.');
            }

            if ($invoiceId === null) {
                throw new InvalidArgumentException('Cada imputación debe indicar una factura.');
            }

            $invoice = Invoice::query()->lockForUpdate()->find($invoiceId);

            if ($invoice === null) {
                throw new InvalidArgumentException("La factura de la imputación #".($index + 1).' no existe.');
            }

            if ((int) $invoice->customer_id !== $customerId) {
                throw new InvalidArgumentException('Todas las facturas imputadas deben pertenecer al mismo cliente del cobro.');
            }

            if ($invoice->status === 'draft' || $invoice->isCancelled()) {
                throw new InvalidArgumentException("La factura {$invoice->invoice_number} no admite pagos.");
            }

            $remaining = $this->invoiceRemainingForAllocation($invoice, $paymentId);

            if ($lineAmount > $remaining) {
                throw new InvalidArgumentException(
                    "La imputación a {$invoice->invoice_number} supera el saldo pendiente ({$remaining} €).",
                );
            }

            $allocatedTotal = round($allocatedTotal + $lineAmount, 2);
        }

        if ($allocatedTotal > $paymentAmount) {
            throw new InvalidArgumentException('La suma imputada no puede superar el importe del cobro.');
        }
    }

    protected function invoiceRemainingForAllocation(Invoice $invoice, ?int $paymentId = null): float
    {
        $allocatedElsewhere = (float) PaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->when($paymentId !== null, fn ($query) => $query->where('payment_id', '!=', $paymentId))
            ->sum('amount');

        return max(0, round($invoice->grossAmount() - $allocatedElsewhere, 2));
    }

    /**
     * @param  list<array{invoice_id?: int|null, amount?: float|int|string}>  $allocations
     */
    protected function persistAllocations(Payment $payment, array $allocations): void
    {
        foreach ($allocations as $allocation) {
            $invoiceId = isset($allocation['invoice_id']) ? (int) $allocation['invoice_id'] : null;
            $lineAmount = round((float) ($allocation['amount'] ?? 0), 2);

            if ($invoiceId === null || $lineAmount <= 0) {
                continue;
            }

            $payment->allocations()->create([
                'invoice_id' => $invoiceId,
                'amount' => $lineAmount,
            ]);
        }
    }

    /**
     * @param  list<array{invoice_id?: int|null, amount?: float|int|string}>  $allocations
     * @return list<int>
     */
    protected function invoiceIdsFromAllocations(array $allocations): array
    {
        return collect($allocations)
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<array{invoice_id?: int|null, amount?: float|int|string}>  $allocations
     */
    protected function resolvePrimaryInvoiceId(array $allocations): ?int
    {
        $invoiceIds = $this->invoiceIdsFromAllocations($allocations);

        return count($invoiceIds) === 1 ? $invoiceIds[0] : null;
    }

    /**
     * @param  list<int>  $invoiceIds
     */
    protected function syncInvoicesFromAllocations(array $invoiceIds): void
    {
        foreach ($invoiceIds as $invoiceId) {
            $this->syncInvoicePaymentStatus($invoiceId);
        }
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function pendingInvoicesForCustomer(int $customerId): Collection
    {
        return Invoice::query()
            ->where('customer_id', $customerId)
            ->where('document_type', 'invoice')
            ->whereNull('cancelled_at')
            ->whereIn('status', ['issued', 'paid'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get()
            ->filter(fn (Invoice $invoice): bool => $invoice->remainingAmount() > 0)
            ->values();
    }
}
