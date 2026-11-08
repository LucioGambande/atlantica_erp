<x-filament-panels::page>
    <form wire:submit="calculate" class="space-y-6">
        {{ $this->form }}

        <x-filament::button type="submit" icon="heroicon-o-calculator">
            Calcular
        </x-filament::button>
    </form>

    @if ($result)
        <x-filament::section heading="Resultado">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 mb-4">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Precio base</div>
                    <div class="text-2xl font-semibold mt-1">{{ \Illuminate\Support\Number::currency($result['base_price'], $result['currency']) }}</div>
                </div>

                @if ($result['zone'])
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Zona</div>
                        <div class="text-2xl font-semibold mt-1">{{ $result['zone'] }}</div>
                    </div>
                @endif

                @if ($result['billable_weight'])
                    <div>
                        <div class="text-sm text-gray-500 dark:text-gray-400">Peso facturable</div>
                        <div class="text-2xl font-semibold mt-1">{{ number_format($result['billable_weight'], 2) }} kg</div>
                    </div>
                @endif
            </div>

            @if (count($result['surcharges']) > 0)
                <div class="mb-4">
                    <div class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Recargos aplicados</div>
                    <ul class="text-sm space-y-1">
                        @foreach ($result['surcharges'] as $surcharge)
                            <li class="flex justify-between border-b border-gray-100 dark:border-gray-800 py-1">
                                <span>{{ $surcharge['concept'] }}</span>
                                <span>{{ \Illuminate\Support\Number::currency($surcharge['amount'], $result['currency']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex justify-between items-center pt-2 border-t border-gray-200 dark:border-gray-700">
                <span class="text-lg font-semibold">Total {{ $result['vat_included'] ? '(IVA incluido)' : '(sin IVA)' }}</span>
                <span class="text-2xl font-bold">{{ \Illuminate\Support\Number::currency($result['total'], $result['currency']) }}</span>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
