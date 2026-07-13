<?php

namespace App\Filament\Support;

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\InvoicePrintAuthorization;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;

class TableUi
{
    public static function configure(Table $table): Table
    {
        return $table
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns([
                'default' => 1,
                'sm' => 2,
                'lg' => 4,
            ])
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->striped();
    }

    /**
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    public static function headerSelectFilter(string $filterName, array $options): array
    {
        return [
            'data-filter-trigger' => $filterName,
            'data-filter-options' => json_encode($options, JSON_UNESCAPED_UNICODE),
        ];
    }

    public static function invoiceLink(TextColumn $column): TextColumn
    {
        return $column
            ->color('primary')
            ->url(function (mixed $record): ?string {
                $invoice = $record instanceof Invoice
                    ? $record
                    : ($record->invoice ?? null);

                if (! $invoice instanceof Invoice) {
                    return null;
                }

                $page = InvoicePrintAuthorization::canManage() ? 'edit' : 'view';

                return InvoiceResource::getUrl($page, ['record' => $invoice]);
            });
    }

    public static function customerLink(TextColumn $column): TextColumn
    {
        return $column
            ->color('primary')
            ->url(function (mixed $record): ?string {
                $customer = $record instanceof Customer
                    ? $record
                    : ($record->customer ?? null);

                if (! $customer instanceof Customer) {
                    return null;
                }

                return CustomerResource::getUrl('edit', ['record' => $customer]);
            });
    }
}
