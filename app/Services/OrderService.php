<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderService
{
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $items = $data['items'] ?? [];

            if ($items === []) {
                throw new InvalidArgumentException('Order items are required.');
            }

            $order = Order::create([
                'customer_id' => $data['customer_id'],
                'status' => $data['status'] ?? 'pending',
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            foreach ($items as $item) {
                $product = Product::query()->findOrFail($item['product_id']);
                $quantity = (int) ($item['quantity'] ?? 0);

                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Item quantity must be greater than zero.');
                }

                $unitPrice = (float) ($item['unit_price'] ?? $product->sale_price);
                $discountPercent = (float) ($item['discount_percent'] ?? 0);
                $discountPercent = max(0, min(100, $discountPercent));
                $totalPrice = round($quantity * $unitPrice * (1 - $discountPercent / 100), 2);

                $order->orderItems()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'discount_percent' => $discountPercent,
                    'unit_price' => $unitPrice,
                    'total_price' => $totalPrice,
                ]);

                $totalAmount += $totalPrice;
            }

            $order->update([
                'total_amount' => round($totalAmount, 2),
            ]);

            return $order->load('orderItems.product');
        });
    }
}
