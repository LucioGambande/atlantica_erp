<?php

namespace App\Filament\Widgets;

use App\Filament\Support\TableUi;
use App\Models\Invoice;
use App\Support\InvoicePrintAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class PendingInvoicesWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return InvoicePrintAuthorization::canPrint();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Facturas pendientes de cobro')
            ->query(
                Invoice::query()
                    ->where('status', 'issued')
                    ->with('customer')
                    ->orderBy('issued_at')
            )
            ->columns([
                TableUi::customerLink(
                    Tables\Columns\TextColumn::make('customer.name')
                        ->label('Cliente')
                        ->searchable()
                        ->sortable(),
                ),
                TableUi::invoiceLink(
                    Tables\Columns\TextColumn::make('invoice_number')
                        ->label('Número')
                        ->searchable()
                        ->sortable(),
                ),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emitida')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.balance')
                    ->label('Saldo cliente')
                    ->money('EUR')
                    ->color(fn (Invoice $record): string => (float) ($record->customer?->balance ?? 0) > 0 ? 'danger' : 'success'),
            ])
            ->defaultSort('issued_at')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Sin facturas pendientes')
            ->emptyStateDescription('No hay facturas emitidas pendientes de cobro.');
    }
}
