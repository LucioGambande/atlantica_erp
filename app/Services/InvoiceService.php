<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        protected StockService $stockService,
        protected InvoiceNumberGenerator $invoiceNumberGenerator,
    ) {}

    public function createFromOrder(Order $order, bool $generatesStockMovement = true): Invoice
    {
        return DB::transaction(function () use ($order, $generatesStockMovement): Invoice {
            $order->loadMissing('orderItems.product', 'customer');

            if ($order->orderItems->isEmpty()) {
                throw new RuntimeException('No se puede facturar un pedido sin líneas.');
            }

            if (! $order->customer?->hasBillingDataForInvoicing()) {
                throw new RuntimeException('El cliente no tiene CUIT/tax ID o dirección fiscal cargados. Completá esos datos antes de facturar.');
            }

            $existingInvoice = Invoice::query()
                ->where('order_id', $order->id)
                ->where('document_type', 'invoice')
                ->whereNull('cancelled_at')
                ->first();

            if ($existingInvoice !== null) {
                throw new RuntimeException('Este pedido ya tiene una factura activa.');
            }

            $invoice = Invoice::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'invoice_number' => $this->invoiceNumberGenerator->next(),
                'document_type' => 'invoice',
                'status' => 'issued',
                'total_amount' => $order->total_amount,
                'generates_stock_movement' => $generatesStockMovement,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($order->orderItems as $orderItem) {
                $invoice->invoiceItems()->create([
                    'product_id' => $orderItem->product_id,
                    'description' => $orderItem->product?->name ?? 'Línea de pedido',
                    'quantity' => $orderItem->quantity,
                    'unit_price' => $orderItem->unit_price,
                    'discount_percent' => $orderItem->discount_percent,
                    'total_price' => $orderItem->total_price,
                ]);
            }

            $invoice->recalculateTotalFromItems();

            app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

            if ($generatesStockMovement) {
                $this->stockService->applyStockFromInvoice($invoice->fresh());
            }

            if ($order->status === 'pending') {
                $order->update(['status' => 'completed']);
            }

            return $invoice->load('invoiceItems.product');
        });
    }

    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->loadMissing('invoiceItems.product');

            if (! $invoice->canBeCancelled()) {
                throw new RuntimeException('Esta factura no se puede cancelar.');
            }

            $creditNote = Invoice::create([
                'customer_id' => $invoice->customer_id,
                'order_id' => $invoice->order_id,
                'credited_invoice_id' => $invoice->id,
                'invoice_number' => $this->invoiceNumberGenerator->next(),
                'document_type' => 'credit_note',
                'status' => 'issued',
                'total_amount' => 0,
                'generates_stock_movement' => $invoice->generates_stock_movement,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($invoice->invoiceItems as $item) {
                $creditNote->invoiceItems()->create([
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => -1 * abs((float) $item->unit_price),
                    'discount_percent' => $item->discount_percent,
                    'total_price' => -1 * abs((float) $item->total_price),
                ]);
            }

            $creditNote->recalculateTotalFromItems();

            app(AccountStatementService::class)->registerInvoice($creditNote->fresh(['invoiceItems', 'customer']));

            if ($invoice->stock_movements_recorded) {
                $this->stockService->reverseStockFromInvoice($creditNote, $invoice);
            }

            $invoice->update(['cancelled_at' => Carbon::now()]);

            return $creditNote->load('invoiceItems.product', 'creditedInvoice');
        });
    }

    public function paginatedInvoices(
        ?int $customerId,
        ?string $customerName,
        ?string $from,
        ?string $to,
        int $perPage,
    ): LengthAwarePaginator {
        $query = $this->filteredBillableInvoices($customerId, $customerName, $from, $to)
            ->with('customer:id,name,fiscal_name')
            ->with('invoiceItems')
            ->withSum('paymentAllocations as payment_allocations_sum_amount', 'amount')
            ->orderByDesc('issued_at');

        return $query->paginate($perPage)->through(fn (Invoice $invoice): array => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'document_type' => $invoice->document_type,
            'status' => $invoice->status,
            'customer' => [
                'id' => $invoice->customer?->id,
                'name' => $invoice->customer?->billingName(),
            ],
            'total_amount' => (float) $invoice->total_amount,
            'gross_amount' => $invoice->grossAmount(),
            'remaining_amount' => $invoice->remainingAmount(),
            'payment_status' => $invoice->paymentStatusLabel(),
            'issued_at' => $invoice->issued_at?->toDateTimeString(),
        ]);
    }

    /**
     * @return array{documents_count: int, invoices_count: int, credit_notes_count: int, net_total: float}
     */
    public function billingSummary(?int $customerId, ?string $customerName, ?string $from, ?string $to): array
    {
        $totals = $this->filteredBillableInvoices($customerId, $customerName, $from, $to)
            ->selectRaw('COUNT(*) as documents_count')
            ->selectRaw("COALESCE(SUM(CASE WHEN document_type = 'invoice' THEN 1 ELSE 0 END), 0) as invoices_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN document_type = 'credit_note' THEN 1 ELSE 0 END), 0) as credit_notes_count")
            ->selectRaw('COALESCE(SUM(total_amount), 0) as net_total')
            ->first();

        return [
            'documents_count' => (int) $totals->documents_count,
            'invoices_count' => (int) $totals->invoices_count,
            'credit_notes_count' => (int) $totals->credit_notes_count,
            'net_total' => round((float) $totals->net_total, 2),
        ];
    }

    /**
     * Facturación agrupada por día o por mes, útil para series temporales / gráficos.
     *
     * @return list<array{period: string, documents_count: int, net_total: float}>
     */
    public function billingTrend(
        ?int $customerId,
        ?string $customerName,
        ?string $from,
        ?string $to,
        string $groupBy = 'day',
    ): array {
        $format = $groupBy === 'month' ? 'Y-m' : 'Y-m-d';

        $rows = $this->filteredBillableInvoices($customerId, $customerName, $from, $to)
            ->get(['issued_at', 'total_amount']);

        return $rows
            ->groupBy(fn (Invoice $invoice): string => $invoice->issued_at?->format($format) ?? 'sin-fecha')
            ->map(fn (Collection $group, string $period): array => [
                'period' => $period,
                'documents_count' => $group->count(),
                'net_total' => round((float) $group->sum('total_amount'), 2),
            ])
            ->sortBy('period')
            ->values()
            ->all();
    }

    protected function filteredBillableInvoices(
        ?int $customerId,
        ?string $customerName,
        ?string $from,
        ?string $to,
    ): Builder {
        return Invoice::query()
            ->whereIn('status', ['issued', 'paid'])
            ->whereNull('cancelled_at')
            ->when($customerId, fn (Builder $query, int $id): Builder => $query->where('customer_id', $id))
            ->when($customerName, fn (Builder $query, string $name): Builder => $query->whereHas(
                'customer',
                fn (Builder $query): Builder => $query->where('name', 'like', "%{$name}%")
                    ->orWhere('fiscal_name', 'like', "%{$name}%"),
            ))
            ->when($from, fn (Builder $query, string $from): Builder => $query->whereDate('issued_at', '>=', $from))
            ->when($to, fn (Builder $query, string $to): Builder => $query->whereDate('issued_at', '<=', $to));
    }
}
