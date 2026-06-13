<?php

namespace App\Services;

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
                $product = $orderItem->product;

                if ($product === null) {
                    throw new DomainException('Order item product could not be resolved.');
                }

                if ($product->stock < $orderItem->quantity) {
                    throw new DomainException("Insufficient stock for product {$product->sku}.");
                }

                $product->decrement('stock', $orderItem->quantity);

                $product->stockMovements()->create([
                    'type' => 'out',
                    'quantity' => $orderItem->quantity,
                    'reference_type' => 'Order',
                    'reference_id' => $order->id,
                ]);
            }
        });
    }
}
