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
    ) {
    }

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
}
