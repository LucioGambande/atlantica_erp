<x-filament-widgets::widget>
    @php
        $customer = $this->getCustomer();
    @endphp

    @if ($customer)
        <x-filament::section>
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-sm text-gray-500 dark:text-gray-400">Saldo de cuenta corriente</div>
                    <div @class([
                        'text-3xl font-bold mt-1',
                        'text-danger-600' => (float) $customer->balance > 0,
                        'text-success-600' => (float) $customer->balance <= 0,
                    ])>
                        {{ \Illuminate\Support\Number::currency((float) $customer->balance, 'EUR') }}
                    </div>
                    <div class="text-sm text-gray-500 mt-2">
                        @if ((float) $customer->balance > 0)
                            El cliente nos debe este importe.
                        @elseif ((float) $customer->balance < 0)
                            Saldo a favor del cliente.
                        @else
                            Cuenta al día.
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <div class="text-gray-500">Última factura</div>
                        @if ($lastInvoice = $this->getLastInvoice())
                            <div class="font-medium">{{ $lastInvoice->invoice_number }}</div>
                            <div class="text-gray-500">{{ $lastInvoice->issued_at?->format('d/m/Y') }}</div>
                        @else
                            <div class="text-gray-400">—</div>
                        @endif
                    </div>
                    <div>
                        <div class="text-gray-500">Último pago</div>
                        @if ($lastPayment = $this->getLastPayment())
                            <div class="font-medium">{{ \Illuminate\Support\Number::currency((float) $lastPayment->amount, 'EUR') }}</div>
                            <div class="text-gray-500">{{ $lastPayment->paid_at?->format('d/m/Y') }}</div>
                        @else
                            <div class="text-gray-400">—</div>
                        @endif
                    </div>
                </div>

                <div>
                    <x-filament::button
                        tag="a"
                        :href="$this->getStatementUrl()"
                        icon="heroicon-o-document-text"
                        color="primary"
                    >
                        Ver cuenta corriente
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-widgets::widget>
