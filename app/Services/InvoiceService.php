<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Support\LineItemTotals;
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

            $isIndividual = $order->customer->customer_type === 'individual';

            // Las ventas a clientes individuales no llevan numeración fiscal
            // propia: quedan como un registro interno (cuenta corriente y
            // pagos funcionan igual que con cualquier cliente), y se
            // agrupan después en la factura mensual "PARTICULAR" a nombre
            // de Consumidor Final. El stock de estos pedidos ya se movió
            // (o se mueve) a nivel de pedido, nunca acá.
            $invoice = Invoice::create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'invoice_number' => $isIndividual ? $this->temporaryInternalNumber() : $this->invoiceNumberGenerator->next(),
                'document_type' => 'invoice',
                'is_fiscal_document' => ! $isIndividual,
                'status' => 'issued',
                'total_amount' => $order->total_amount,
                'generates_stock_movement' => $isIndividual ? false : $generatesStockMovement,
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

            if ($isIndividual) {
                $invoice->update(['invoice_number' => $this->internalNumberFor($invoice)]);
            }

            $invoice->recalculateTotalFromItems();

            app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

            if (! $isIndividual && $generatesStockMovement) {
                $this->stockService->applyStockFromInvoice($invoice->fresh());
            }

            if ($order->status === 'pending') {
                $order->update(['status' => 'completed']);
            }

            return $invoice->load('invoiceItems.product');
        });
    }

    /**
     * Número provisorio y único para una venta interna, antes de conocer
     * su ID definitivo (ver internalNumberFor()).
     */
    protected function temporaryInternalNumber(): string
    {
        return 'INTERNO-PENDIENTE-'.bin2hex(random_bytes(6));
    }

    protected function internalNumberFor(Invoice $invoice): string
    {
        return 'INTERNO-'.str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
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
                'invoice_number' => $invoice->is_fiscal_document ? $this->invoiceNumberGenerator->next() : $this->temporaryInternalNumber(),
                'document_type' => 'credit_note',
                'is_fiscal_document' => $invoice->is_fiscal_document,
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

            if (! $creditNote->is_fiscal_document) {
                $creditNote->update(['invoice_number' => $this->internalNumberFor($creditNote)]);
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

    /**
     * Crea una nota de crédito por devolución parcial (o total, si se
     * indican todas las cantidades disponibles) de una factura. A
     * diferencia de cancelInvoice(), la factura original no queda
     * cancelada: sigue vigente por lo que no se devolvió.
     *
     * @param  list<array{invoice_item_id: int, quantity: int}>  $lines
     */
    public function createPartialCreditNote(Invoice $invoice, array $lines): Invoice
    {
        return DB::transaction(function () use ($invoice, $lines): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $invoice->loadMissing('invoiceItems.product', 'invoiceItems.creditNoteItems');

            if (! $invoice->canBeCredited()) {
                throw new RuntimeException('Esta factura no admite devoluciones.');
            }

            $itemsById = $invoice->invoiceItems->keyBy('id');
            $toCredit = [];

            foreach ($lines as $line) {
                $item = $itemsById->get((int) ($line['invoice_item_id'] ?? 0));
                $quantity = (int) ($line['quantity'] ?? 0);

                if ($item === null || $quantity <= 0) {
                    continue;
                }

                $available = $item->remainingReturnableQuantity();

                if ($quantity > $available) {
                    throw new RuntimeException(
                        "No podés devolver {$quantity} u. de \"{$item->description}\": solo quedan {$available} disponibles."
                    );
                }

                $toCredit[] = ['item' => $item, 'quantity' => $quantity];
            }

            if ($toCredit === []) {
                throw new RuntimeException('Indicá al menos una cantidad a devolver.');
            }

            $creditNote = Invoice::create([
                'customer_id' => $invoice->customer_id,
                'order_id' => $invoice->order_id,
                'credited_invoice_id' => $invoice->id,
                'invoice_number' => $invoice->is_fiscal_document ? $this->invoiceNumberGenerator->next() : $this->temporaryInternalNumber(),
                'document_type' => 'credit_note',
                'is_fiscal_document' => $invoice->is_fiscal_document,
                'status' => 'issued',
                'total_amount' => 0,
                'generates_stock_movement' => $invoice->generates_stock_movement,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($toCredit as $entry) {
                /** @var InvoiceItem $item */
                $item = $entry['item'];
                $quantity = $entry['quantity'];
                $unitPrice = -1 * abs((float) $item->unit_price);

                $creditNote->invoiceItems()->create([
                    'credited_invoice_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'description' => $item->description,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'discount_percent' => $item->discount_percent,
                    'total_price' => LineItemTotals::discountedLineTotal($unitPrice, $quantity, (float) $item->discount_percent),
                ]);
            }

            if (! $creditNote->is_fiscal_document) {
                $creditNote->update(['invoice_number' => $this->internalNumberFor($creditNote)]);
            }

            // Invoice::create() dispara el observer, que ya cacheó la
            // relación "invoiceItems" vacía (todavía no existían las
            // líneas). Forzamos un reload antes de recalcular para no
            // guardar total_amount = 0.
            $creditNote->load('invoiceItems');
            $creditNote->recalculateTotalFromItems();

            app(AccountStatementService::class)->registerInvoice($creditNote->fresh(['invoiceItems', 'customer']));

            if ($invoice->stock_movements_recorded && $invoice->generates_stock_movement) {
                $this->stockService->recordMovementsForInvoice(
                    $creditNote->fresh(['invoiceItems.product']),
                    enforceStockAvailability: false,
                );
                $creditNote->update(['stock_movements_recorded' => true]);
            }

            return $creditNote->load('invoiceItems.product', 'creditedInvoice');
        });
    }

    /**
     * Agrupa las ventas internas (sin numeración fiscal) hechas a clientes
     * `individual` en un período, en una única factura real emitida a
     * "Consumidor Final", con la serie PARTICULAR. No vuelve a mover
     * stock: ya se movió al nivel de cada pedido.
     */
    public function createMonthlyIndividualSummaryInvoice(Customer $consumidorFinal, Carbon $from, Carbon $to): Invoice
    {
        return DB::transaction(function () use ($consumidorFinal, $from, $to): Invoice {
            $internalInvoices = Invoice::query()
                ->where('is_fiscal_document', false)
                ->whereNull('cancelled_at')
                ->whereNull('consolidated_into_invoice_id')
                ->where('status', '!=', 'draft')
                ->whereBetween('issued_at', [$from, $to])
                ->whereHas('customer', fn (Builder $query): Builder => $query->where('customer_type', 'individual'))
                ->with('invoiceItems', 'customer')
                ->orderBy('issued_at')
                ->get();

            if ($internalInvoices->isEmpty()) {
                throw new RuntimeException('No hay ventas a clientes individuales en ese período para consolidar.');
            }

            if (! $consumidorFinal->hasBillingDataForInvoicing()) {
                throw new RuntimeException('El cliente "Consumidor Final" no tiene CUIT/tax ID o dirección fiscal cargados. Completá esos datos antes de generar la factura.');
            }

            $invoice = Invoice::create([
                'customer_id' => $consumidorFinal->id,
                'invoice_number' => $this->invoiceNumberGenerator->next($this->invoiceNumberGenerator->particularPrefix()),
                'document_type' => 'invoice',
                'is_fiscal_document' => true,
                'status' => 'issued',
                'total_amount' => 0,
                'generates_stock_movement' => false,
                'issued_at' => Carbon::now(),
            ]);

            foreach ($internalInvoices as $internal) {
                $invoice->invoiceItems()->create([
                    'product_id' => null,
                    'description' => sprintf(
                        'Venta a %s del %s (ref. %s)',
                        $internal->customer?->name ?? 'cliente individual',
                        $internal->issued_at?->format('d/m/Y') ?? '—',
                        $internal->invoice_number,
                    ),
                    'quantity' => 1,
                    'unit_price' => $internal->netAmount(),
                    'discount_percent' => 0,
                    'total_price' => $internal->netAmount(),
                ]);
            }

            $invoice->recalculateTotalFromItems();

            app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

            Invoice::query()
                ->whereIn('id', $internalInvoices->pluck('id'))
                ->update(['consolidated_into_invoice_id' => $invoice->id]);

            return $invoice->load('invoiceItems', 'consolidatedInvoices');
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
            // Las ventas internas a clientes individuales (sin numeración
            // fiscal) no cuentan acá: ya quedan reflejadas, una sola vez,
            // en la factura PARTICULAR mensual que las agrupa.
            ->where('is_fiscal_document', true)
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
