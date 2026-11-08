<x-filament-panels::page>
    @php
        $summary = $this->getSummary();
        $warehouseTotals = $this->getWarehouseTotals();
        $warehouseNames = \App\Models\Warehouse::query()->pluck('name', 'id');
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

    @if ($warehouseTotals->isNotEmpty())
        <x-filament::section class="mb-6">
            <x-slot name="heading">Totales por almacén</x-slot>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($warehouseTotals as $row)
                    <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $row->warehouse_id !== null ? ($warehouseNames[$row->warehouse_id] ?? 'Almacén #'.$row->warehouse_id) : 'Sin almacén asignado' }}
                        </div>
                        <div class="text-xl font-semibold mt-1">{{ number_format((int) $row->total_units) }} uds.</div>
                        <div class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Number::currency((float) $row->stock_value, 'EUR') }}</div>
                    </div>
                @endforeach
                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">Total general</div>
                    <div class="text-xl font-semibold mt-1">{{ number_format((int) $warehouseTotals->sum('total_units')) }} uds.</div>
                    <div class="text-xs text-gray-500 mt-1">{{ \Illuminate\Support\Number::currency((float) $warehouseTotals->sum('stock_value'), 'EUR') }}</div>
                </div>
            </div>
        </x-filament::section>
    @endif

    {{ $this->table }}
</x-filament-panels::page>
