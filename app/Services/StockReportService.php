<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
    public function summary(?int $warehouseId = null): array
    {
        if ($warehouseId === null) {
            $products = Product::query()->get();

            $lastMovementAt = StockMovement::query()->max('created_at');

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

        // Con almacén, no se puede usar products.stock (es el total
        // global): el saldo de cada producto en ese almacén se calcula
        // sobre el log de movimientos, igual que Product::stockBalanceInWarehouse().
        $balances = Product::query()
            ->with([])
            ->get()
            ->map(fn (Product $product): array => [
                'product' => $product,
                'stock' => $product->stockBalanceInWarehouse($warehouseId),
            ]);

        $lastMovementAt = StockMovement::query()
            ->where('warehouse_id', $warehouseId)
            ->max('created_at');

        return [
            'products_count' => $balances->where('stock', '!=', 0)->count(),
            'total_units' => (int) $balances->sum('stock'),
            'zero_stock_count' => $balances->where('stock', '<=', 0)->count(),
            'low_stock_count' => $balances
                ->where('stock', '>', 0)
                ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->count(),
            'stock_value' => round($balances->sum(
                fn (array $row): float => (float) $row['stock'] * (float) $row['product']->sale_price,
            ), 2),
            'last_movement_at' => $lastMovementAt !== null ? Carbon::parse($lastMovementAt) : null,
        ];
    }

    /**
     * Totales por almacén (+ un bucket `warehouse_id = null` para
     * movimientos históricos sin asignar), para el bloque de totales del
     * reporte. Calculado on-the-fly sobre el log de movimientos, sin
     * tabla de cache: el volumen esperado (productos × pocos almacenes)
     * no lo justifica.
     *
     * @return Collection<int, object>
     */
    public function totalsByWarehouse(): Collection
    {
        return StockMovement::query()
            ->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->selectRaw('stock_movements.warehouse_id')
            ->selectRaw("SUM(CASE WHEN stock_movements.type = 'in' THEN stock_movements.quantity ELSE -stock_movements.quantity END) as total_units")
            ->selectRaw("SUM((CASE WHEN stock_movements.type = 'in' THEN stock_movements.quantity ELSE -stock_movements.quantity END) * products.sale_price) as stock_value")
            ->groupBy('stock_movements.warehouse_id')
            ->get();
    }
}
