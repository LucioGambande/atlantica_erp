<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use DomainException;
use Illuminate\Support\Facades\DB;

class StockService
{
    public const REFERENCE_INVOICE = 'Invoice';

    public const REFERENCE_ORDER = 'Order';

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
