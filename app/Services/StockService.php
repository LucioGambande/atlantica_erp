<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockService
{
    public const REFERENCE_INVOICE = 'Invoice';

    public const REFERENCE_ORDER = 'Order';

    public const REFERENCE_PURCHASE_INVOICE = 'PurchaseInvoice';

    public const REFERENCE_STOCK_TRANSFER = 'StockTransfer';

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
                    lotId: $orderItem->lot_id,
                    warehouseId: $order->warehouse_id,
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
                    $this->incrementProductStock($product, $quantity, self::REFERENCE_INVOICE, $invoice->id, $item->lot_id, $invoice->warehouse_id);
                } else {
                    $this->createMovement($product, 'in', $quantity, self::REFERENCE_INVOICE, $invoice->id, $item->lot_id, $invoice->warehouse_id);
                }
            } elseif ($updateProductStock) {
                $this->reduceProductStock(
                    $product,
                    $quantity,
                    self::REFERENCE_INVOICE,
                    $invoice->id,
                    $enforceStockAvailability,
                    $item->lot_id,
                    $invoice->warehouse_id,
                );
            } else {
                $this->createMovement($product, 'out', $quantity, self::REFERENCE_INVOICE, $invoice->id, $item->lot_id, $invoice->warehouse_id);
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

    /**
     * Alinea el stock con el estado de la factura de compra: suma la
     * mercadería cuando pasa a recibida/pagada y la revierte si vuelve a
     * borrador. Idempotente — se puede llamar en cada guardado.
     */
    public function syncStockForPurchaseInvoice(PurchaseInvoice $purchaseInvoice): void
    {
        $purchaseInvoice->refresh();

        if ($purchaseInvoice->hasEnteredStock() && $purchaseInvoice->generates_stock_movement) {
            $this->applyStockFromPurchaseInvoice($purchaseInvoice);

            return;
        }

        if ($purchaseInvoice->stock_movements_recorded) {
            $this->reverseStockFromPurchaseInvoice($purchaseInvoice);
        }
    }

    public function applyStockFromPurchaseInvoice(PurchaseInvoice $purchaseInvoice): void
    {
        DB::transaction(function () use ($purchaseInvoice): void {
            $purchaseInvoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($purchaseInvoice->id);

            if (! $purchaseInvoice->generates_stock_movement || $purchaseInvoice->stock_movements_recorded) {
                return;
            }

            // Sin líneas de mercadería todavía no hay nada que registrar: no
            // marcamos la compra como registrada para que el stock entre
            // cuando se carguen las líneas.
            if ($this->recordPurchaseInvoiceEntries($purchaseInvoice) === 0) {
                return;
            }

            $purchaseInvoice->update([
                'stock_movements_recorded' => true,
                'received_at' => $purchaseInvoice->received_at ?? now(),
            ]);
        });
    }

    /**
     * Revierte con movimientos compensatorios de salida, en vez de borrar los
     * de entrada: el log queda contando lo que realmente pasó.
     *
     * Se revierte lo que esta compra REGISTRÓ, no lo que dicen sus líneas. Una
     * compra anterior a esta funcionalidad puede estar marcada como registrada
     * sin tener movimientos propios (su mercadería se cargó a mano): en ese
     * caso no hay nada que devolver y restar por las líneas sacaría del
     * depósito stock que esta compra nunca sumó.
     */
    public function reverseStockFromPurchaseInvoice(PurchaseInvoice $purchaseInvoice): void
    {
        DB::transaction(function () use ($purchaseInvoice): void {
            $purchaseInvoice = PurchaseInvoice::query()->lockForUpdate()->findOrFail($purchaseInvoice->id);

            if (! $purchaseInvoice->stock_movements_recorded) {
                return;
            }

            foreach ($this->netMovementsFor($purchaseInvoice) as $entry) {
                $product = Product::query()->find($entry->product_id);

                if ($product === null || (int) $entry->net <= 0) {
                    continue;
                }

                // Devolver mercadería puede dejar el saldo negativo si ya se
                // vendió: lo registramos igual, como en las ventas.
                $this->reduceProductStock(
                    $product,
                    (int) $entry->net,
                    self::REFERENCE_PURCHASE_INVOICE,
                    $purchaseInvoice->id,
                    enforceStockAvailability: false,
                    lotId: $entry->lot_id,
                    warehouseId: $entry->warehouse_id,
                );
            }

            $purchaseInvoice->update(['stock_movements_recorded' => false]);
        });
    }

    /**
     * Saldo neto por producto y lote de los movimientos que generó esta
     * compra. Netear (y no mirar solo las entradas) hace que recibir y
     * revertir varias veces no acumule reversiones de más.
     *
     * @return Collection<int, object>
     */
    protected function netMovementsFor(PurchaseInvoice $purchaseInvoice)
    {
        return StockMovement::query()
            ->where('reference_type', self::REFERENCE_PURCHASE_INVOICE)
            ->where('reference_id', $purchaseInvoice->id)
            ->selectRaw("product_id, lot_id, warehouse_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as net")
            ->groupBy('product_id', 'lot_id', 'warehouse_id')
            ->get();
    }

    /**
     * @return int cantidad de líneas que movieron stock
     */
    protected function recordPurchaseInvoiceEntries(PurchaseInvoice $purchaseInvoice): int
    {
        $purchaseInvoice->load('purchaseInvoiceItems.product');
        $moved = 0;

        foreach ($purchaseInvoice->purchaseInvoiceItems as $item) {
            $product = $item->product;
            $quantity = abs((int) $item->quantity);

            // Las líneas sin producto (servicios, portes, recargos) no son
            // mercadería: no mueven stock.
            if ($product === null || $quantity <= 0) {
                continue;
            }

            $this->incrementProductStock(
                $product,
                $quantity,
                self::REFERENCE_PURCHASE_INVOICE,
                $purchaseInvoice->id,
                $item->lot_id,
                $purchaseInvoice->warehouse_id,
            );

            $moved++;
        }

        return $moved;
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
        ?int $lotId = null,
        ?int $warehouseId = null,
    ): void {
        if ($product === null) {
            throw new DomainException('No se pudo resolver el producto de la línea.');
        }

        if ($enforceStockAvailability && $product->stock < $quantity) {
            throw new DomainException("Stock insuficiente para el producto {$product->sku}.");
        }

        $product->decrement('stock', $quantity);

        $this->createMovement($product, 'out', $quantity, $referenceType, $referenceId, $lotId, $warehouseId);
    }

    protected function incrementProductStock(
        Product $product,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?int $lotId = null,
        ?int $warehouseId = null,
    ): void {
        $product->increment('stock', $quantity);

        $this->createMovement($product, 'in', $quantity, $referenceType, $referenceId, $lotId, $warehouseId);
    }

    protected function createMovement(
        Product $product,
        string $type,
        int $quantity,
        string $referenceType,
        int $referenceId,
        ?int $lotId = null,
        ?int $warehouseId = null,
    ): void {
        $product->stockMovements()->create([
            'lot_id' => $lotId,
            'warehouse_id' => $warehouseId,
            'type' => $type,
            'quantity' => $quantity,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
        ]);
    }

    /**
     * Saldo de un producto en un almacén puntual, derivado del log de
     * movimientos (no de products.stock, que es el total global).
     */
    public function warehouseBalance(Product $product, int $warehouseId, ?int $lotId = null): int
    {
        return $product->stockBalanceInWarehouse($warehouseId, $lotId);
    }

    /**
     * Aplica un traslado completado: genera una salida en el almacén de
     * origen y una entrada en el de destino por cada línea. No toca
     * products.stock (el total global de la empresa no cambia, solo se
     * redistribuye entre almacenes). Idempotente — se puede llamar en cada
     * guardado.
     */
    public function applyStockTransfer(StockTransfer $transfer, bool $enforceStockAvailability = true): void
    {
        DB::transaction(function () use ($transfer, $enforceStockAvailability): void {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if ($transfer->status !== 'completed') {
                return;
            }

            if ($transfer->stock_movements_recorded) {
                return;
            }

            if ($transfer->origin_warehouse_id === $transfer->destination_warehouse_id) {
                throw new DomainException('El almacén de destino debe ser distinto al de origen.');
            }

            $transfer->loadMissing('stockTransferItems.product');

            if ($transfer->stockTransferItems->isEmpty()) {
                throw new DomainException('El traslado no tiene líneas.');
            }

            foreach ($transfer->stockTransferItems as $item) {
                $product = $item->product;
                $quantity = abs((int) $item->quantity);

                if ($product === null || $quantity <= 0) {
                    continue;
                }

                if ($enforceStockAvailability) {
                    $available = $this->warehouseBalance($product, $transfer->origin_warehouse_id, $item->lot_id);

                    if ($available < $quantity) {
                        throw new DomainException("Stock insuficiente en el almacén de origen para {$product->sku}.");
                    }
                }

                $this->createMovement($product, 'out', $quantity, self::REFERENCE_STOCK_TRANSFER, $transfer->id, $item->lot_id, $transfer->origin_warehouse_id);
                $this->createMovement($product, 'in', $quantity, self::REFERENCE_STOCK_TRANSFER, $transfer->id, $item->lot_id, $transfer->destination_warehouse_id);
            }

            $transfer->update([
                'stock_movements_recorded' => true,
                'transferred_at' => $transfer->transferred_at ?? now(),
            ]);
        });
    }

    /**
     * Saldo neto por producto, lote y almacén de los movimientos que
     * generó este traslado. Agrupar por almacén (y no solo por
     * producto/lote) hace que la reversión sea correcta incluso si se
     * cambió el origen/destino del header después de completarlo: lee de
     * dónde salió/entró realmente el stock, no lo que diga el header hoy.
     *
     * @return Collection<int, object>
     */
    protected function netTransferMovementsFor(StockTransfer $transfer)
    {
        return StockMovement::query()
            ->where('reference_type', self::REFERENCE_STOCK_TRANSFER)
            ->where('reference_id', $transfer->id)
            ->selectRaw("product_id, lot_id, warehouse_id, SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END) as net")
            ->groupBy('product_id', 'lot_id', 'warehouse_id')
            ->get();
    }

    /**
     * Revierte un traslado con movimientos compensatorios, nunca borrando
     * los originales: el log queda contando lo que realmente pasó.
     */
    public function reverseStockTransfer(StockTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $transfer = StockTransfer::query()->lockForUpdate()->findOrFail($transfer->id);

            if (! $transfer->stock_movements_recorded) {
                return;
            }

            foreach ($this->netTransferMovementsFor($transfer) as $entry) {
                $product = Product::query()->find($entry->product_id);

                if ($product === null || (int) $entry->net === 0) {
                    continue;
                }

                // net > 0 en esa combinación => quedó con entrada neta
                // (destino): se compensa con salida. net < 0 => quedó con
                // salida neta (origen): se compensa con entrada.
                $this->createMovement(
                    $product,
                    $entry->net > 0 ? 'out' : 'in',
                    abs((int) $entry->net),
                    self::REFERENCE_STOCK_TRANSFER,
                    $transfer->id,
                    $entry->lot_id,
                    $entry->warehouse_id,
                );
            }

            $transfer->update(['stock_movements_recorded' => false]);
        });
    }

    /**
     * Alinea el stock con el estado del traslado: aplica los movimientos
     * cuando se completa y los revierte si vuelve a borrador. Idempotente
     * — se puede llamar en cada guardado.
     */
    public function syncStockForTransfer(StockTransfer $transfer): void
    {
        $transfer->refresh();

        if ($transfer->status === 'completed') {
            $this->applyStockTransfer($transfer);

            return;
        }

        if ($transfer->stock_movements_recorded) {
            $this->reverseStockTransfer($transfer);
        }
    }
}
