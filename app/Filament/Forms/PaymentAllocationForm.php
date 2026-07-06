<?php

namespace App\Filament\Forms;

use App\Models\Invoice;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Number;

class PaymentAllocationForm
{
    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function allocationsRepeater(?int $customerId = null): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('allocations')
            ->label('Imputación a facturas')
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Factura')
                    ->options(function (Get $get) use ($customerId): array {
                        $resolvedCustomerId = $customerId
                            ?? (int) ($get('../../customer_id') ?? 0);

                        return static::invoiceOptions($resolvedCustomerId);
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        if (blank($state)) {
                            return;
                        }

                        $invoice = Invoice::query()->find((int) $state);

                        if ($invoice === null) {
                            return;
                        }

                        $set('amount', $invoice->remainingAmount());
                    }),
                Forms\Components\TextInput::make('amount')
                    ->label('Importe imputado')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel('Agregar factura')
            ->reorderable(false)
            ->helperText('La suma imputada puede ser menor al importe del cobro. El resto queda como anticipo del cliente.');
    }

    public static function allocatedSummaryPlaceholder(): Forms\Components\Placeholder
    {
        return Forms\Components\Placeholder::make('allocation_summary')
            ->label('Resumen de imputación')
            ->content(function (Get $get): string {
                $paymentAmount = round((float) ($get('amount') ?? 0), 2);
                $allocated = collect($get('allocations') ?? [])
                    ->sum(fn (array $row): float => round((float) ($row['amount'] ?? 0), 2));
                $unallocated = max(0, round($paymentAmount - $allocated, 2));

                return 'Imputado: '.Number::currency($allocated, 'EUR')
                    .' · Sin asignar: '.Number::currency($unallocated, 'EUR');
            });
    }

    /**
     * @return array<string, string>
     */
    public static function invoiceOptions(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        return app(PaymentService::class)
            ->pendingInvoicesForCustomer($customerId)
            ->mapWithKeys(function (Invoice $invoice): array {
                $label = $invoice->invoice_number
                    .' — pendiente '.Number::currency($invoice->remainingAmount(), 'EUR');

                return [$invoice->id => $label];
            })
            ->all();
    }
}
