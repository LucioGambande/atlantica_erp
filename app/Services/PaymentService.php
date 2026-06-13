<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PaymentService
{
    public function registerPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data): Payment {
            $amount = round((float) ($data['amount'] ?? 0), 2);

            if ($amount <= 0) {
                throw new InvalidArgumentException('Payment amount must be greater than zero.');
            }

            $payment = Payment::create([
                'customer_id' => $data['customer_id'],
                'invoice_id' => $data['invoice_id'] ?? null,
                'amount' => $amount,
                'payment_method' => $data['payment_method'] ?? null,
                'paid_at' => $data['paid_at'],
            ]);

            if ($payment->invoice_id !== null) {
                $invoice = Invoice::query()->findOrFail($payment->invoice_id);

                if ((int) $invoice->customer_id !== (int) $payment->customer_id) {
                    throw new InvalidArgumentException('Payment customer does not match invoice customer.');
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

            return $payment->load('invoice', 'customer');
        });
    }
}
