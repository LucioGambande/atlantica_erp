<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class StockSanitizerService
{
    public const REFERENCE_INVOICE = 'Invoice';

    public function __construct(
        protected StockService $stockService,
    ) {}

    /**
     * @return array{
     *     invoices_flagged: int,
     *     movements_removed: int,
     *     movements_created: int,
     *     products_recalculated: int,
     *     dry_run: bool
     * }
     */
    public function sanitize(bool $dryRun = false): array
    {
        $stats = [
            'invoices_flagged' => 0,
            'movements_removed' => 0,
            'movements_created' => 0,
            'products_recalculated' => 0,
            'dry_run' => $dryRun,
        ];

        if ($dryRun) {
            return $this->preview($stats);
        }

        return DB::transaction(function () use ($stats): array {
            $stats['invoices_flagged'] = $this->flagStockAffectingInvoices();

            $validInvoiceIds = Invoice::query()
                ->where('generates_stock_movement', true)
                ->pluck('id');

            $stats['movements_removed'] = StockMovement::query()
                // Las entradas por factura de compra quedan fuera del saneo:
                // tienen su propio respaldo documental y su propio flag de
                // idempotencia. Borrarlas vaciaría el depósito sin que ninguna
                // compra pueda volver a registrarlas.
                ->where(function ($query): void {
                    $query
                        ->where('reference_type', '!=', StockService::REFERENCE_PURCHASE_INVOICE)
                        ->orWhereNull('reference_type');
                })
                ->where(function ($query) use ($validInvoiceIds): void {
                    $query
                        ->where('reference_type', '!=', self::REFERENCE_INVOICE)
                        ->orWhereNull('reference_type')
                        ->orWhereNotIn('reference_id', $validInvoiceIds);
                })
                ->delete();

            Invoice::query()->update(['stock_movements_recorded' => false]);

            $invoices = $this->stockAffectingInvoices();

            foreach ($invoices as $invoice) {
                $stats['movements_created'] += $this->stockService->recordMovementsForInvoice(
                    $invoice,
                    enforceStockAvailability: false,
                    updateProductStock: false,
                );
                $invoice->updateQuietly(['stock_movements_recorded' => true]);
            }

            $stats['products_recalculated'] = $this->stockService->recalculateAllProductStockFromMovements();

            return $stats;
        });
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    protected function preview(array $stats): array
    {
        $stats['invoices_flagged'] = $this->countInvoicesToFlag();

        $validInvoiceIds = Invoice::query()
            ->where(function ($query): void {
                $query->whereIn('status', ['issued', 'paid']);
            })
            ->pluck('id');

        $stats['movements_removed'] = StockMovement::query()
            ->where(function ($query): void {
                $query
                    ->where('reference_type', '!=', StockService::REFERENCE_PURCHASE_INVOICE)
                    ->orWhereNull('reference_type');
            })
            ->where(function ($query) use ($validInvoiceIds): void {
                $query
                    ->where('reference_type', '!=', self::REFERENCE_INVOICE)
                    ->orWhereNull('reference_type')
                    ->orWhereNotIn('reference_id', $validInvoiceIds);
            })
            ->count();

        $stats['movements_created'] = Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->with('invoiceItems')
            ->get()
            ->sum(fn (Invoice $invoice): int => $invoice->invoiceItems
                ->whereNotNull('product_id')
                ->count());

        $stats['products_recalculated'] = Product::query()->count();

        return $stats;
    }

    protected function flagStockAffectingInvoices(): int
    {
        $enabled = Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->where('generates_stock_movement', false)
            ->update(['generates_stock_movement' => true]);

        $disabled = Invoice::query()
            ->where('status', 'draft')
            ->where('generates_stock_movement', true)
            ->update(['generates_stock_movement' => false]);

        return $enabled + $disabled;
    }

    protected function countInvoicesToFlag(): int
    {
        $enabled = Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->where('generates_stock_movement', false)
            ->count();

        $disabled = Invoice::query()
            ->where('status', 'draft')
            ->where('generates_stock_movement', true)
            ->count();

        return $enabled + $disabled;
    }

    /**
     * @return Collection<int, Invoice>
     */
    protected function stockAffectingInvoices(): Collection
    {
        return Invoice::query()
            ->where('generates_stock_movement', true)
            ->whereIn('status', ['issued', 'paid'])
            ->with(['invoiceItems.product'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * Genera, una sola vez, los movimientos de facturas emitidas/pagadas que
     * TODAVÍA no tienen ningún movimiento propio (el caso legacy: se
     * cargaron con generates_stock_movement=false porque en su momento no
     * se llevaba control de stock). Se guía por la presencia real de
     * StockMovement, no por el flag — evita depender de un flag cuyo
     * significado histórico es ambiguo.
     *
     * No toca facturas que ya tienen movimientos (nunca duplica) ni corre
     * ningún DELETE: a diferencia de sanitize(), es puramente aditivo, así
     * que no hay riesgo de arrastrar movimientos de Order/StockTransfer.
     * Cada movimiento queda fechado con el issued_at real de su factura.
     *
     * Ajusta products.stock con decrement/increment (updateProductStock:
     * true), nunca recalculateAllProductStockFromMovements(): esa
     * recalculación suma SOLO el log de movimientos, así que pisaría
     * cualquier stock de partida que un producto tenga sin un movimiento
     * que lo respalde (ej. un stock inicial cargado a mano al crear el
     * producto). Este método es puramente aditivo sobre lo que ya hay.
     *
     * @return array{
     *     invoices_found: int,
     *     invoices_processed: int,
     *     movements_created: int,
     *     skipped_individual: int,
     *     dry_run: bool
     * }
     */
    public function backfillMissingInvoiceMovements(bool $dryRun = false): array
    {
        $stats = [
            'invoices_found' => 0,
            'invoices_processed' => 0,
            'movements_created' => 0,
            'skipped_individual' => 0,
            'dry_run' => $dryRun,
        ];

        $invoices = $this->invoicesMissingStockMovements();
        $stats['invoices_found'] = $invoices->count();

        if ($dryRun) {
            $stats['skipped_individual'] = $invoices
                ->filter(fn (Invoice $invoice): bool => $invoice->customer?->customer_type === 'individual')
                ->count();

            return $stats;
        }

        return DB::transaction(function () use ($stats, $invoices): array {
            foreach ($invoices as $invoice) {
                // Resguardo: en estos clientes el stock ya se movió a nivel
                // del pedido (reduceStockFromOrder), nunca a nivel de la
                // factura — crearle un movimiento acá duplicaría la salida.
                if ($invoice->customer?->customer_type === 'individual') {
                    $stats['skipped_individual']++;

                    continue;
                }

                $created = $this->stockService->recordMovementsForInvoice(
                    $invoice,
                    enforceStockAvailability: false,
                    updateProductStock: true,
                );

                if ($created > 0 && $invoice->issued_at !== null) {
                    StockMovement::query()
                        ->where('reference_type', self::REFERENCE_INVOICE)
                        ->where('reference_id', $invoice->id)
                        ->update([
                            'created_at' => $invoice->issued_at,
                            'updated_at' => $invoice->issued_at,
                        ]);
                }

                $invoice->updateQuietly([
                    'generates_stock_movement' => true,
                    'stock_movements_recorded' => true,
                ]);

                $stats['invoices_processed']++;
                $stats['movements_created'] += $created;
            }

            return $stats;
        });
    }

    /**
     * @return Collection<int, Invoice>
     */
    protected function invoicesMissingStockMovements(): Collection
    {
        $invoiceIdsWithMovements = StockMovement::query()
            ->where('reference_type', self::REFERENCE_INVOICE)
            ->distinct()
            ->pluck('reference_id');

        return Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->whereNotIn('id', $invoiceIdsWithMovements)
            ->with(['invoiceItems.product', 'customer'])
            ->orderBy('issued_at')
            ->orderBy('id')
            ->get();
    }
}
