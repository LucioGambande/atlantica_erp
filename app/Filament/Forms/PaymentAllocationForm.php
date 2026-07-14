<?php

namespace App\Filament\Forms;

use App\Models\Invoice;
use App\Services\PaymentService;
use App\Support\InvoiceLabel;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\Number;

class PaymentAllocationForm
{
    /**
     * Bloque completo de imputación: selector rápido, importe, resumen y detalle.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function allocationFields(?int $customerId = null): array
    {
        return [
            static::quickInvoiceMultiSelect($customerId),
            static::paymentAmountInput(),
            static::allocatedSummaryPlaceholder(),
            static::allocationsRepeater($customerId),
        ];
    }

    public static function quickInvoiceMultiSelect(?int $customerId = null): Forms\Components\Select
    {
        return Forms\Components\Select::make('quick_invoice_ids')
            ->label('Facturas a liquidar')
            ->helperText('Seleccioná una o más facturas para cobrar el importe pendiente completo. El importe del cobro y las imputaciones se completan solos.')
            ->multiple()
            ->searchable()
            ->options(function (Get $get) use ($customerId): array {
                $extraIds = collect($get('allocations') ?? [])
                    ->pluck('invoice_id')
                    ->filter()
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                return static::invoiceOptions(static::resolveCustomerId($get, $customerId), $extraIds);
            })
            ->getOptionLabelsUsing(fn (array $values): array => static::resolveInvoiceLabels($values))
            ->visible(function (Get $get) use ($customerId): bool {
                return static::resolveCustomerId($get, $customerId) > 0;
            })
            ->live()
            ->afterStateUpdated(function (?array $state, Set $set): void {
                static::syncAllocationsFromInvoiceIds($state ?? [], $set);
            })
            ->dehydrated(false);
    }

    public static function paymentAmountInput(): Forms\Components\TextInput
    {
        return Forms\Components\TextInput::make('amount')
            ->label('Importe del cobro')
            ->required()
            ->numeric()
            ->minValue(0.01)
            ->step(0.01)
            ->live(onBlur: true)
            ->helperText('Se completa al elegir facturas. Podés aumentarlo si el cliente paga de más (anticipo).');
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function allocationsRepeater(?int $customerId = null): Forms\Components\Repeater
    {
        return Forms\Components\Repeater::make('allocations')
            ->label('Detalle de imputación')
            ->schema([
                Forms\Components\Select::make('invoice_id')
                    ->label('Factura')
                    ->options(function (Get $get) use ($customerId): array {
                        $extraIds = collect($get('allocations') ?? [])
                            ->pluck('invoice_id')
                            ->filter()
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        return static::invoiceOptions(static::resolveCustomerId($get, $customerId), $extraIds);
                    })
                    ->getOptionLabelUsing(fn ($value): ?string => static::resolveInvoiceLabel($value))
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                        if (blank($state)) {
                            static::syncPaymentAmountFromAllocations($set, $get);
                            static::syncQuickInvoiceIdsFromAllocations($set, $get);

                            return;
                        }

                        $invoice = Invoice::query()->find((int) $state);

                        if ($invoice !== null) {
                            $set('amount', $invoice->remainingAmount());
                        }

                        static::syncPaymentAmountFromAllocations($set, $get);
                        static::syncQuickInvoiceIdsFromAllocations($set, $get);
                    }),
                Forms\Components\TextInput::make('amount')
                    ->label('Importe imputado')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        static::syncPaymentAmountFromAllocations($set, $get);
                    }),
            ])
            ->columns(2)
            ->defaultItems(0)
            ->addActionLabel('Agregar factura')
            ->reorderable(false)
            ->live()
            ->afterStateUpdated(function (?array $state, Set $set, Get $get): void {
                static::syncPaymentAmountFromAllocations($set, $get);
                static::syncQuickInvoiceIdsFromAllocations($set, $get);
            })
            ->helperText('Ajustá importes parciales por factura si hace falta. La suma imputada puede ser menor al cobro (anticipo).');
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

    public static function resetAllocationFields(Set $set): void
    {
        $set('quick_invoice_ids', []);
        $set('allocations', []);
        $set('amount', null);
    }

    /**
     * @param  list<int|string>  $invoiceIds
     */
    public static function syncAllocationsFromInvoiceIds(array $invoiceIds, Set $set): void
    {
        $allocations = collect($invoiceIds)
            ->map(function ($invoiceId): ?array {
                $invoice = Invoice::query()->find((int) $invoiceId);

                if ($invoice === null) {
                    return null;
                }

                return [
                    'invoice_id' => (string) $invoiceId,
                    'amount' => $invoice->remainingAmount(),
                ];
            })
            ->filter()
            ->values()
            ->all();

        $set('allocations', $allocations);

        $total = round(collect($allocations)->sum(fn (array $row): float => (float) $row['amount']), 2);

        if ($total > 0) {
            $set('amount', $total);
        }
    }

    public static function syncPaymentAmountFromAllocations(Set $set, Get $get): void
    {
        $allocated = round(collect($get('allocations') ?? [])
            ->sum(fn (array $row): float => (float) ($row['amount'] ?? 0)), 2);

        if ($allocated > 0) {
            $set('amount', $allocated);
        }
    }

    public static function syncQuickInvoiceIdsFromAllocations(Set $set, Get $get): void
    {
        $invoiceIds = collect($get('allocations') ?? [])
            ->pluck('invoice_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $set('quick_invoice_ids', $invoiceIds);
    }

    /**
     * @param  list<int>  $includeInvoiceIds
     * @return array<string, string>
     */
    public static function invoiceOptions(int $customerId, array $includeInvoiceIds = []): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $pending = app(PaymentService::class)->pendingInvoicesForCustomer($customerId);

        $extra = $includeInvoiceIds === []
            ? collect()
            : Invoice::query()
                ->where('customer_id', $customerId)
                ->whereIn('id', $includeInvoiceIds)
                ->get();

        return $pending
            ->merge($extra)
            ->unique('id')
            ->sortBy('issued_at')
            ->sortBy('id')
            ->mapWithKeys(function (Invoice $invoice): array {
                return [(string) $invoice->id => InvoiceLabel::withPendingAmount($invoice)];
            })
            ->all();
    }

    public static function resolveInvoiceLabel(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $invoice = Invoice::query()->find((int) $value);

        if ($invoice === null) {
            return (string) $value;
        }

        return InvoiceLabel::withPendingAmount($invoice);
    }

    /**
     * @param  list<int|string>  $values
     * @return array<string, string>
     */
    public static function resolveInvoiceLabels(array $values): array
    {
        return collect($values)
            ->mapWithKeys(fn ($value): array => [(string) $value => static::resolveInvoiceLabel($value) ?? (string) $value])
            ->all();
    }

    protected static function resolveCustomerId(Get $get, ?int $customerId): int
    {
        if ($customerId !== null && $customerId > 0) {
            return $customerId;
        }

        return (int) ($get('customer_id') ?? 0);
    }
}
