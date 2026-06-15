<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceNumberGenerator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {
    }

    public function createFromOrder(Order $order, bool $generatesStockMovement = false): Invoice
    {
        return DB::transaction(function () use ($order, $generatesStockMovement): Invoice {
            $order->loadMissing('orderItems.product');

            if ($order->orderItems->isEmpty()) {
                throw new RuntimeException('No se puede facturar un pedido sin líneas.');
            }

            $existingInvoice = Invoice::query()
                ->where('order_id', $order->id)
                ->where('document_type', 'invoice')
                ->whereNull('cancelled_at')
                ->first();

            if ($existingInvoice !== null) {
                throw new RuntimeException('Este pedido ya tiene una factura activa.');
            }

            $invoice = Invoice::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'invoice_number' => $this->invoiceNumberGenerator->next(),
                'document_type' => 'invoice',
                'status' => 'issued',
                'total_amount' => $order->total_amount,
                'generates_stock_movement' => $generatesStockMovement,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($order->orderItems as $orderItem) {
                $invoice->invoiceItems()->create([
                    'product_id' => $orderItem->product_id,
                    'description' => $orderItem->product?->name ?? 'Línea de pedido',
                    'quantity' => $orderItem->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'discount_percent' => $orderItem->discount_percent,
                    'total_price' => $orderItem->total_price,
                ]);
            }

            $invoice->recalculateTotalFromItems();

            if ($generatesStockMovement) {
                $this->stockService->applyStockFromInvoice($invoice);
            }

            if ($order->status === 'pending') {
                $order->update(['status' => 'completed']);
            }

            return $invoice->load('invoiceItems.product');
        });
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->loadMissing('invoiceItems.product');

            if (! $invoice->canBeCancelled()) {
                throw new RuntimeException('Esta factura no se puede cancelar.');
            }

            $creditNote = Invoice::create([
                'customer_id' => $invoice->customer_id,
                'order_id' => $invoice->order_id,
                'credited_invoice_id' => $invoice->id,
                'invoice_number' => $this->invoiceNumberGenerator->next(),
                'document_type' => 'credit_note',
                'status' => 'issued',
                'total_amount' => 0,
                'generates_stock_movement' => $invoice->generates_stock_movement,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($invoice->invoiceItems as $item) {
                $creditNote->invoiceItems()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => -1 * abs((float) $item->unit_price),
                    'discount_percent' => $item->discount_percent,
                    'total_price' => -1 * abs((float) $item->total_price),
                ]);
            }

            $creditNote->recalculateTotalFromItems();

            if ($invoice->stock_movements_recorded) {
                $this->stockService->reverseStockFromInvoice($creditNote, $invoice);
            }

            $invoice->update(['cancelled_at' => Carbon::now()]);

            return $creditNote->load('invoiceItems.product', 'creditedInvoice');
        });
    }
}
