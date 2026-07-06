<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Carbon;
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
    ): Payment {
        return DB::transaction(function () use ($invoice, $paymentMethodId, $detail, $paidAt): Payment {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if ($invoice->status === 'draft') {
                throw new InvalidArgumentException('No se puede registrar un pago en una factura en borrador.');
            }

            if ($invoice->status === 'paid') {
                throw new InvalidArgumentException('La factura ya está pagada.');
            }

            $remaining = $invoice->remainingAmount();

            if ($remaining <= 0) {
                throw new InvalidArgumentException('La factura no tiene saldo pendiente.');
            }

            return $this->registerPayment([
                'customer_id' => $invoice->customer_id,
                'invoice_id' => $invoice->id,
                'payment_method_id' => $paymentMethodId,
                'amount' => $remaining,
                'detail' => $detail,
                'paid_at' => $paidAt ?? now(),
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

            $detail = $this->paymentDetailService->createForMethod(
                $paymentMethod,
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
            );

            $payment = Payment::create([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'payment_method_id' => $paymentMethod->id,
                'detail_type' => $paymentMethod->detail_type,
                'detail_id' => $detail->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'],
            ]);

            if ($payment->invoice_id !== null) {
                $invoice = Invoice::query()->findOrFail($payment->invoice_id);

                if ((int) $invoice->customer_id !== (int) $payment->customer_id) {
                    throw new InvalidArgumentException('El cliente del pago no coincide con el de la factura.');
                }

                $paidAmount = (float) Payment::query()
                    ->where('invoice_id', $invoice->id)
                    ->sum('amount');

                if ($paidAmount >= (float) $invoice->total_amount) {
                    $invoice->update([
                        'status' => 'paid',
                    ]);
                }
            }

            return $payment->load('invoice', 'customer', 'paymentMethod', 'detail');
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

            $previousInvoiceId = $payment->invoice_id;

            $this->paymentDetailService->updateForPayment(
                $payment,
                $paymentMethod,
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
            );

            $payment->update([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'payment_method_id' => $paymentMethod->id,
                'amount' => $amount,
                'paid_at' => $data['paid_at'],
            ]);

            if ($payment->invoice_id !== null) {
                $invoice = Invoice::query()->findOrFail($payment->invoice_id);

                if ((int) $invoice->customer_id !== (int) $payment->customer_id) {
                    throw new InvalidArgumentException('El cliente del pago no coincide con el de la factura.');
                }
            }

            $this->syncInvoicePaymentStatus($payment->invoice_id);

            if ($previousInvoiceId !== null && (int) $previousInvoiceId !== (int) $payment->invoice_id) {
                $this->syncInvoicePaymentStatus($previousInvoiceId);
            }

            return $payment->fresh(['invoice', 'customer', 'paymentMethod', 'detail']);
        });
    }

    protected function syncInvoicePaymentStatus(?int $invoiceId): void
    {
        if ($invoiceId === null) {
            return;
        }

        $invoice = Invoice::query()->find($invoiceId);

        if ($invoice === null || $invoice->status === 'draft') {
            return;
        }

        $paidAmount = (float) Payment::query()
            ->where('invoice_id', $invoice->id)
            ->sum('amount');

        $invoice->update([
            'status' => $paidAmount >= (float) $invoice->total_amount ? 'paid' : 'issued',
        ]);
    }
}
