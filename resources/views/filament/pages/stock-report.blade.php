<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5 mb-6">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Productos</div>
            <div class="text-2xl font-semibold mt-1">{{ $summary['products_count'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Unidades en stock</div>
            <div class="text-2xl font-semibold mt-1">{{ number_format($summary['total_units']) }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Sin stock</div>
            <div class="text-2xl font-semibold mt-1 text-danger-600">{{ $summary['zero_stock_count'] }}</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Stock bajo</div>
            <div class="text-2xl font-semibold mt-1 text-warning-600">{{ $summary['low_stock_count'] }}</div>
            <div class="text-xs text-gray-500 mt-1">1–{{ \App\Services\StockReportService::LOW_STOCK_THRESHOLD }} uds.</div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Valor a PVP</div>
            <div class="text-2xl font-semibold mt-1">{{ \Illuminate\Support\Number::currency($summary['stock_value'], 'EUR') }}</div>
            @if ($summary['last_movement_at'])
                <div class="text-xs text-gray-500 mt-1">
                    Últ. mov.: {{ $summary['last_movement_at']->format('d/m/Y H:i') }}
                </div>
            @endif
        </x-filament::section>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
