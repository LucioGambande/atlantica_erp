<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Support\StatusBadge;
use App\Filament\Support\TableUi;
use App\Models\Customer;
use App\Models\Invoice;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerInvoicesWidget extends BaseWidget
{
    protected static ?string $heading = 'Facturas';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function getCustomer(): ?Customer
    {
        return $this->record instanceof Customer ? $this->record : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getCustomer()
                ? $this->getCustomer()->invoices()->getQuery()
                : Invoice::query()->whereRaw('1 = 0'))
            ->columns([
                TableUi::invoiceLink(
                    Tables\Columns\TextColumn::make('invoice_number')
                        ->label('Número')
                        ->sortable(),
                ),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'credit_note' => 'Nota de crédito',
                        default => 'Factura',
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (Invoice $record): string => match (true) {
                        $record->isCancelled() => 'Cancelada',
                        $record->status === 'draft' => 'Borrador',
                        $record->status === 'issued' => 'Emitida',
                        $record->status === 'paid' => 'Pagada',
                        default => $record->status,
                    }),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emitida')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Total')
                    ->state(fn (Invoice $record): float => $record->grossAmount())
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('settlement_status')
                    ->label('Cobro')
                    ->badge()
                    ->state(fn (Invoice $record): ?string => $record->settlementStatus())
                    ->color(fn (?string $state): string => StatusBadge::settlement($state))
                    ->placeholder('—'),
            ])
            ->defaultSort('issued_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Ver')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Invoice $record): string => InvoiceResource::getUrl('edit', ['record' => $record])),
            ])
            ->bulkActions([]);
    }
}
