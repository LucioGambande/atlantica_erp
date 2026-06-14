<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function reduceStockFromOrder(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->loadMissing('orderItems.product');

            if ($order->orderItems->isEmpty()) {
                throw new DomainException('Cannot reduce stock for an order without items.');
            }

            $alreadyProcessed = $order->orderItems()
                ->whereHas('product.stockMovements', function ($query) use ($order) {
                    $query
                        ->where('type', 'out')
                        ->where('reference_type', 'Order')
                        ->where('reference_id', $order->id);
                })
                ->exists();

            if ($alreadyProcessed) {
                throw new DomainException('Stock has already been reduced for this order.');
            }

            foreach ($order->orderItems as $orderItem) {
                $this->reduceProductStock(
                    $orderItem->product,
                    (int) $orderItem->quantity,
                    'Order',
                    $order->id,
                );
            }
        });
    }

    public function applyStockFromInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->loadMissing('invoiceItems.product');

            if (! $invoice->generates_stock_movement) {
                return;
            }

            if ($invoice->stock_movements_recorded) {
                throw new DomainException('El stock ya fue registrado para esta factura.');
            }

            if ($invoice->invoiceItems->isEmpty()) {
                throw new DomainException('No se puede registrar stock en una factura sin líneas.');
            }

            foreach ($invoice->invoiceItems as $item) {
                $this->reduceProductStock(
                    $item->product,
                    (int) $item->quantity,
                    'Invoice',
                    $invoice->id,
                );
            }

            $invoice->update(['stock_movements_recorded' => true]);
        });
    }

    public function reverseStockFromInvoice(Invoice $creditNote, Invoice $originalInvoice): void
    {
        DB::transaction(function () use ($creditNote, $originalInvoice): void {
            $creditNote->loadMissing('invoiceItems.product');

            if ($creditNote->stock_movements_recorded) {
                return;
            }

            foreach ($creditNote->invoiceItems as $item) {
                $product = $item->product;

                if ($product === null) {
                    continue;
                }

                $quantity = abs((int) $item->quantity);

                $product->increment('stock', $quantity);

                $product->stockMovements()->create([
                    'type' => 'in',
                    'quantity' => $quantity,
                    'reference_type' => 'Invoice',
                    'reference_id' => $creditNote->id,
                ]);
            }

            $creditNote->update(['stock_movements_recorded' => true]);
            $originalInvoice->update(['stock_movements_recorded' => false]);
        });
    }

    protected function reduceProductStock(?\App\Models\Product $product, int $quantity, string $referenceType, int $referenceId): void
    {
        if ($product === null) {
            throw new DomainException('No se pudo resolver el producto de la línea.');
        }

        if ($product->stock < $quantity) {
            throw new DomainException("Stock insuficiente para el producto {$product->sku}.");
        }

        $product->decrement('stock', $quantity);

        $product->stockMovements()->create([
            'type' => 'out',
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
