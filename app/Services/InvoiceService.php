<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class InvoiceService
{
    public function createFromOrder(Order $order): Invoice
    {
        return DB::transaction(function () use ($order): Invoice {
            $order->loadMissing('orderItems.product');

            if ($order->orderItems->isEmpty()) {
                throw new RuntimeException('Cannot create an invoice without order items.');
            }

            $existingInvoice = Invoice::query()
                ->where('order_id', $order->id)
                ->first();

            if ($existingInvoice !== null) {
                throw new RuntimeException('An invoice already exists for this order.');
            }

            $invoice = Invoice::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'status' => 'issued',
                'total_amount' => $order->total_amount,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($order->orderItems as $orderItem) {
                $invoice->invoiceItems()->create([
                    'product_id' => $orderItem->product_id,
                    'description' => $orderItem->product?->name ?? 'Order item',
                    'quantity' => $orderItem->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'total_price' => $orderItem->total_price,
                ]);
            }

            if ($order->status === 'pending') {
                $order->update([
                    'status' => 'completed',
                ]);
            }

            return $invoice->load('invoiceItems.product');
        });
    }

    protected function generateInvoiceNumber(): string
    {
        do {
            $invoiceNumber = 'INV-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (
            Invoice::query()->where('invoice_number', $invoiceNumber)->exists()
        );

        return $invoiceNumber;
    }
}
