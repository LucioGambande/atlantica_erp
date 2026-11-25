@php
    $vatRate = $invoice->vatRate();
@endphp

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="text-left text-xs font-medium text-gray-500 dark:text-gray-400">
                <th class="py-2 pr-2">Producto</th>
                <th class="py-2 pr-2 text-right">Cant.</th>
                <th class="py-2 pr-2 text-right">P. unit. (sin IVA)</th>
                <th class="py-2 pr-2 text-right">Dto.</th>
                <th class="py-2 pr-2 text-right">Subtotal (sin IVA)</th>
                <th class="py-2 pr-2 text-right">Subtotal (con IVA)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @forelse ($invoice->invoiceItems as $item)
                <tr>
                    <td class="py-2 pr-2">{{ $item->product?->name ?? $item->description }}</td>
                    <td class="py-2 pr-2 text-right">{{ $item->quantity }}</td>
                    <td class="py-2 pr-2 text-right">{{ number_format((float) $item->unit_price, 2, ',', '.') }} €</td>
                    <td class="py-2 pr-2 text-right">{{ number_format((float) $item->discount_percent, 2, ',', '.') }} %</td>
                    <td class="py-2 pr-2 text-right">{{ number_format($item->discounted_total, 2, ',', '.') }} €</td>
                    <td class="py-2 pr-2 text-right">{{ number_format($item->discounted_total * (1 + $vatRate), 2, ',', '.') }} €</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="py-4 text-center text-gray-500">Sin líneas registradas.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot class="border-t border-gray-200 font-semibold dark:border-white/10">
            <tr>
                <td colspan="4"></td>
                <td class="py-2 pr-2 text-right">{{ number_format($invoice->netAmount(), 2, ',', '.') }} €</td>
                <td class="py-2 pr-2 text-right">{{ number_format($invoice->grossAmount(), 2, ',', '.') }} €</td>
            </tr>
        </tfoot>
    </table>
</div>
