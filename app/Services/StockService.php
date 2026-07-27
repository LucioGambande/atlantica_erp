<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class StockService
{
    public const REFERENCE_INVOICE = 'Invoice';

    public const REFERENCE_ORDER = 'Order';

    public function paginatedStock(?string $search, bool $onlyLowStock, int $perPage): LengthAwarePaginator
    {
        return Product::query()
            ->when($search, fn (Builder $query, string $search): Builder => $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            }))
            ->when($onlyLowStock, fn (Builder $query): Builder => $query->where('stock', '<=', 0))
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return array{products_count: int, total_units: int, total_value_purchase: float, total_value_sale: float, products_out_of_stock: int}
     */
    public function stockSummary(): array
    {
        $totals = Product::query()
            ->selectRaw('COUNT(*) as products_count')
            ->selectRaw('COALESCE(SUM(stock), 0) as total_units')
            ->selectRaw('COALESCE(SUM(stock * purchase_price), 0) as total_value_purchase')
            ->selectRaw('COALESCE(SUM(stock * sale_price), 0) as total_value_sale')
            ->selectRaw('COALESCE(SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END), 0) as products_out_of_stock')
            ->first();

        return [
            'products_count' => (int) $totals->products_count,
            'total_units' => (int) $totals->total_units,
            'total_value_purchase' => round((float) $totals->total_value_purchase, 2),
            'total_value_sale' => round((float) $totals->total_value_sale, 2),
            'products_out_of_stock' => (int) $totals->products_out_of_stock,
        ];
    }

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
                        ->where('reference_type', self::REFERENCE_ORDER)
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
                    self::REFERENCE_ORDER,
                    $order->id,
                );
            }
        });
    }

    public function applyStockFromInvoice(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice): void {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $invoice->generates_stock_movement) {
                return;
            }

            if ($invoice->stock_movements_recorded) {
                throw new DomainException('El stock ya fue registrado para esta factura.');
            }

            // Permitimos facturar con movimiento de stock aunque el saldo quede negativo.
            $this->recordMovementsForInvoice($invoice, enforceStockAvailability: false);

            $invoice->update(['stock_movements_recorded' => true]);
        });
    }

    public function recordMovementsForInvoice(
        Invoice $invoice,
        bool $enforceStockAvailability = true,
        bool $updateProductStock = true,
    ): int {
        $invoice->loadMissing('invoiceItems.product');
        $created = 0;

        foreach ($invoice->invoiceItems as $item) {
            $product = $item->product;

            if ($product === null) {
                continue;
            }

            $quantity = abs((int) $item->quantity);

            if ($quantity <= 0) {
                continue;
            }

            if ($invoice->isCreditNote()) {
                if ($updateProductStock) {
                    $this->incrementProductStock($product, $quantity, self::REFERENCE_INVOICE, $invoice->id);
                } else {
                    $this->createMovement($product, 'in', $quantity, self::REFERENCE_INVOICE, $invoice->id);
                }
            } elseif ($updateProductStock) {
                $this->reduceProductStock(
                    $product,
                    $quantity,
                    self::REFERENCE_INVOICE,
                    $invoice->id,
                    $enforceStockAvailability,
                );
            } else {
                $this->createMovement($product, 'out', $quantity, self::REFERENCE_INVOICE, $invoice->id);
            }

            $created++;
        }

        return $created;
    }

    public function reverseStockFromInvoice(Invoice $creditNote, Invoice $originalInvoice): void
    {
        DB::transaction(function () use ($creditNote, $originalInvoice): void {
            if ($creditNote->stock_movements_recorded) {
                return;
            }

            $this->recordMovementsForInvoice($creditNote, enforceStockAvailability: false);

            $creditNote->update(['stock_movements_recorded' => true]);
            $originalInvoice->update(['stock_movements_recorded' => false]);
        });
    }

    public function recalculateAllProductStockFromMovements(): int
    {
        $updated = 0;

        Product::query()->each(function (Product $product) use (&$updated): void {
            $this->recalculateProductStockFromMovements($product);
            $updated++;
        });

        return $updated;
    }

    public function recalculateProductStockFromMovements(Product $product): void
    {
        $balance = (int) $product->stockMovements()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0) as balance")
            ->value('balance');

        $product->update(['stock' => $balance]);
    }

    protected function reduceProductStock(
        ?Product $product,
        int $quantity,
        string $referenceType,
        int $referenceId,
        bool $enforceStockAvailability = true,
    ): void {
        if ($product === null) {
            throw new DomainException('No se pudo resolver el producto de la línea.');
        }

        if ($enforceStockAvailability && $product->stock < $quantity) {
            throw new DomainException("Stock insuficiente para el producto {$product->sku}.");
        }

        $product->decrement('stock', $quantity);

        $this->createMovement($product, 'out', $quantity, $referenceType, $referenceId);
    }

    protected function incrementProductStock(
        Product $product,
        int $quantity,
        string $referenceType,
        int $referenceId,
    ): void {
        $product->increment('stock', $quantity);

        $this->createMovement($product, 'in', $quantity, $referenceType, $referenceId);
    }

    protected function createMovement(
        Product $product,
        string $type,
        int $quantity,
        string $referenceType,
        int $referenceId,
    ): void {
        $product->stockMovements()->create([
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }
}
