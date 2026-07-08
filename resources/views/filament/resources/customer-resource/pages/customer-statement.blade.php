<x-filament-panels::page>
    @php
        $summary = $this->getStatementSummary();
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total facturado</div>
            <div class="text-2xl font-semibold mt-1">
                {{ \Illuminate\Support\Number::currency($summary['total_invoiced'], 'EUR') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Total cobrado</div>
            <div class="text-2xl font-semibold mt-1 text-success-600">
                {{ \Illuminate\Support\Number::currency($summary['total_paid'], 'EUR') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Saldo pendiente</div>
            <div @class([
                'text-2xl font-bold mt-1',
                'text-danger-600' => $summary['balance_due'] > 0,
                'text-success-600' => $summary['balance_due'] <= 0,
            ])>
                {{ \Illuminate\Support\Number::currency($summary['balance_due'], 'EUR') }}
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="text-sm text-gray-500 dark:text-gray-400">Facturas impagas</div>
            <div class="text-2xl font-semibold mt-1 text-warning-600">
                {{ \Illuminate\Support\Number::currency($summary['overdue_amount'], 'EUR') }}
            </div>
        </x-filament::section>
    </div>

        <x-filament::section class="mb-6">
        <x-slot name="heading">Filtros</x-slot>
        {{ $this->filtersForm }}
        @if ($this->excludeSettledInvoices)
            <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                Mostrando solo movimientos con facturas pendientes o parciales. El saldo corrido por fila se oculta en esta vista; usá los totales del período al final.
            </p>
        @endif
    </x-filament::section>

    {{ $this->table }}

    <x-filament::section class="mt-6">
        <x-slot name="heading">Totales del período</x-slot>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <div class="text-sm text-gray-500">Total débitos</div>
                <div class="text-lg font-semibold text-danger-600">
                    {{ \Illuminate\Support\Number::currency($summary['total_debit'], 'EUR') }}
                </div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Total créditos</div>
                <div class="text-lg font-semibold text-success-600">
                    {{ \Illuminate\Support\Number::currency($summary['total_credit'], 'EUR') }}
                </div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Saldo final del período</div>
                <div class="text-lg font-bold">
                    {{ \Illuminate\Support\Number::currency($summary['final_balance'], 'EUR') }}
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-panels::page>
