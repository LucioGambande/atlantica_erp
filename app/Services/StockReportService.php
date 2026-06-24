<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Carbon;

class StockReportService
{
    public const LOW_STOCK_THRESHOLD = 10;

    /**
     * @return array{
     *     products_count: int,
     *     total_units: int,
     *     zero_stock_count: int,
     *     low_stock_count: int,
     *     stock_value: float,
     *     last_movement_at: ?Carbon
     * }
     */
    public function summary(): array
    {
        $products = Product::query()->get();

        $lastMovementAt = \App\Models\StockMovement::query()->max('created_at');

        return [
            'products_count' => $products->count(),
            'total_units' => (int) $products->sum('stock'),
            'zero_stock_count' => $products->where('stock', '<=', 0)->count(),
            'low_stock_count' => $products
                ->where('stock', '>', 0)
                ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
            'stock_value' => round($products->sum(
                fn (Product $product): float => (float) $product->stock * (float) $product->sale_price,
            ), 2),
            'last_movement_at' => $lastMovementAt !== null ? Carbon::parse($lastMovementAt) : null,
        ];
    }
}
