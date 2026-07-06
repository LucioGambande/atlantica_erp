<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentAllocation;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceItem;
use App\Models\StockMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TestProductPurgeService
{
    /**
     * @return array{
     *     prefix: string,
     *     products: int,
     *     invoice_items: int,
     *     order_items: int,
     *     stock_movements: int,
     *     price_list_items: int,
     *     purchase_invoice_items: int,
     *     products_list: list<array{sku: string, name: string}>
     * }
     */
    public function preview(string $prefix): array
    {
        $products = $this->matchingProducts($prefix);
        $productIds = $products->pluck('id');

        return [
            'prefix' => $prefix,
            'products' => $products->count(),
            'invoice_items' => InvoiceItem::query()->whereIn('product_id', $productIds)->count(),
            'order_items' => OrderItem::query()->whereIn('product_id', $productIds)->count(),
            'stock_movements' => StockMovement::query()->whereIn('product_id', $productIds)->count(),
            'price_list_items' => PriceListItem::query()->whereIn('product_id', $productIds)->count(),
            'purchase_invoice_items' => PurchaseInvoiceItem::query()->whereIn('product_id', $productIds)->count(),
            'products_list' => $products
                ->map(fn (Product $product): array => [
                    'sku' => $product->sku,
                    'name' => $product->name,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     prefix: string,
     *     products_deleted: int,
     *     invoice_items_deleted: int,
     *     invoices_recalculated: int,
     *     invoices_deleted: int,
     *     order_items_deleted: int,
     *     orders_deleted: int,
     *     stock_movements_deleted: int,
     *     price_list_items_deleted: int,
     *     purchase_invoice_items_deleted: int,
     *     customers_rebuilt: int
     * }
     */
    public function purge(string $prefix, bool $dryRun = false): array
    {
        $preview = $this->preview($prefix);
        $products = $this->matchingProducts($prefix);
        $productIds = $products->pluck('id');

        $stats = [
            'prefix' => $prefix,
            'products_deleted' => 0,
            'invoice_items_deleted' => 0,
            'invoices_recalculated' => 0,
            'invoices_deleted' => 0,
            'order_items_deleted' => 0,
            'orders_deleted' => 0,
            'stock_movements_deleted' => 0,
            'price_list_items_deleted' => 0,
            'purchase_invoice_items_deleted' => 0,
            'customers_rebuilt' => 0,
        ];

        if ($products->isEmpty()) {
            return $stats;
        }

        if ($dryRun) {
            return [
                ...$stats,
                'products_deleted' => $preview['products'],
                'invoice_items_deleted' => $preview['invoice_items'],
                'order_items_deleted' => $preview['order_items'],
                'stock_movements_deleted' => $preview['stock_movements'],
                'price_list_items_deleted' => $preview['price_list_items'],
                'purchase_invoice_items_deleted' => $preview['purchase_invoice_items'],
            ];
        }

        $affectedCustomerIds = collect();

        DB::transaction(function () use ($productIds, $products, &$stats, &$affectedCustomerIds): void {
            $invoiceIds = InvoiceItem::query()
                ->whereIn('product_id', $productIds)
                ->pluck('invoice_id')
                ->unique();

            $stats['invoice_items_deleted'] = InvoiceItem::query()
                ->whereIn('product_id', $productIds)
                ->delete();

            Invoice::withoutEvents(function () use ($invoiceIds, &$stats, &$affectedCustomerIds): void {
                foreach ($invoiceIds as $invoiceId) {
                    $invoice = Invoice::query()->with('invoiceItems')->find($invoiceId);

                    if ($invoice === null) {
                        continue;
                    }

                    $affectedCustomerIds->push($invoice->customer_id);

                    if ($invoice->invoiceItems->isEmpty()) {
                        PaymentAllocation::query()
                            ->where('invoice_id', $invoice->id)
                            ->delete();

                        $invoice->delete();
                        $stats['invoices_deleted']++;

                        continue;
                    }

                    $invoice->recalculateTotalFromItems();
                    $stats['invoices_recalculated']++;
                }
            });

            $orderIds = OrderItem::query()
                ->whereIn('product_id', $productIds)
                ->pluck('order_id')
                ->unique();

            $stats['order_items_deleted'] = OrderItem::query()
                ->whereIn('product_id', $productIds)
                ->delete();

            foreach ($orderIds as $orderId) {
                $order = Order::query()->with('orderItems')->find($orderId);

                if ($order === null) {
                    continue;
                }

                if ($order->orderItems->isEmpty()) {
                    $order->delete();
                    $stats['orders_deleted']++;

                    continue;
                }

                $order->recalculateTotalFromItems();
            }

            $purchaseInvoiceIds = PurchaseInvoiceItem::query()
                ->whereIn('product_id', $productIds)
                ->pluck('purchase_invoice_id')
                ->unique();

            $stats['purchase_invoice_items_deleted'] = PurchaseInvoiceItem::query()
                ->whereIn('product_id', $productIds)
                ->delete();

            foreach ($purchaseInvoiceIds as $purchaseInvoiceId) {
                $purchaseInvoice = PurchaseInvoice::query()
                    ->with('purchaseInvoiceItems')
                    ->find($purchaseInvoiceId);

                if ($purchaseInvoice === null) {
                    continue;
                }

                $total = $purchaseInvoice->purchaseInvoiceItems->sum(
                    fn (PurchaseInvoiceItem $item): float => (float) $item->total_price,
                );

                if ($purchaseInvoice->purchaseInvoiceItems->isEmpty()) {
                    $purchaseInvoice->delete();

                    continue;
                }

                $purchaseInvoice->update(['total_amount' => round($total, 2)]);
            }

            $stats['stock_movements_deleted'] = StockMovement::query()
                ->whereIn('product_id', $productIds)
                ->delete();

            $stats['price_list_items_deleted'] = PriceListItem::query()
                ->whereIn('product_id', $productIds)
                ->delete();

            foreach ($products as $product) {
                $product->forceDelete();
                $stats['products_deleted']++;
            }
        });

        $accountStatementService = app(AccountStatementService::class);

        foreach ($affectedCustomerIds->unique()->filter() as $customerId) {
            $customer = \App\Models\Customer::query()->find($customerId);

            if ($customer === null) {
                continue;
            }

            $accountStatementService->rebuildLedger($customer);
            $stats['customers_rebuilt']++;
        }

        return $stats;
    }

    /**
     * @return Collection<int, Product>
     */
    protected function matchingProducts(string $prefix): Collection
    {
        return Product::query()
            ->withTrashed()
            ->where('sku', 'like', $prefix.'%')
            ->orderBy('sku')
            ->get();
    }
}
