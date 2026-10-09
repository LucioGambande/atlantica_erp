<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Carbon;

class OrderPrintService
{
    public function logoBase64(): ?string
    {
        $relativePath = (string) config('invoices.logo_path', 'images/brand/atlantica-terranova-logo.png');
        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $contents = file_get_contents($absolutePath);

        if ($contents === false) {
            return null;
        }

        return base64_encode($contents);
    }

    public function printableStatuses(): array
    {
        return ['pending', 'completed'];
    }

    public function findForPrint(int $orderId): Order
    {
        return Order::query()
            ->with(['customer', 'orderItems.product', 'orderItems.lot'])
            ->whereIn('status', $this->printableStatuses())
            ->findOrFail($orderId);
    }

    public function pdfFilename(Order $order): string
    {
        return 'Albaran-'.$order->id.'.pdf';
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPrintData(Order $order, bool $withPrices): array
    {
        $vatRate = $order->vatRate();
        $issuer = config('invoices.issuer', []);
        $customer = $order->customer;

        $lines = $order->orderItems->map(fn ($item): array => [
            'description' => $item->product?->name ?? 'Línea',
            'lot' => $item->lot?->code,
            'quantity' => (int) $item->quantity,
            'unit_price' => round((float) $item->unit_price, 2),
            'vat_rate' => $vatRate,
            'discount_percent' => round((float) $item->discount_percent, 2),
            'line_total' => round((float) $item->discounted_total, 2),
        ]);

        $subtotal = round($lines->sum(fn (array $line): float => $line['line_total']), 2);
        $vatAmount = round($subtotal * $vatRate, 2);
        $total = round($subtotal + $vatAmount, 2);

        return [
            'order' => $order,
            'title' => 'ALBARÁN',
            'document_number' => 'Albaran-'.$order->id,
            'with_prices' => $withPrices,
            'ordered_at' => Carbon::parse($order->ordered_at ?? $order->created_at),
            'issuer' => $issuer,
            'iban' => $issuer['iban'] ?? null,
            'customer' => [
                'name' => $customer?->name ?? '—',
                'address' => $customer?->address,
                'tax_id' => $customer?->tax_id,
                'postal_code' => $customer?->postal_code,
                'city' => $customer?->city,
                'email' => $customer?->email,
            ],
            'lines' => $lines,
            'subtotal' => $subtotal,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'vat_rate' => $vatRate,
        ];
    }
}
