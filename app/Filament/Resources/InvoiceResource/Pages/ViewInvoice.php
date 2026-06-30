<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (): bool => InvoiceResource::canPrintInvoice($this->getRecord()))
                ->url(fn (): string => route('invoices.print', $this->getRecord()))
                ->openUrlInNewTab(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Factura')
                    ->schema([
                        Infolists\Components\TextEntry::make('invoice_number')
                            ->label('Número'),
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('document_type')
                            ->label('Tipo')
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'credit_note' => 'Nota de crédito',
                                default => 'Factura',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->formatStateUsing(fn ($record): string => match (true) {
                                $record->isCancelled() => 'Cancelada',
                                $record->status === 'draft' => 'Borrador',
                                $record->status === 'issued' => 'Emitida',
                                $record->status === 'paid' => 'Pagada',
                                default => $record->status,
                            }),
                        Infolists\Components\TextEntry::make('issued_at')
                            ->label('Fecha de emisión')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('total_amount')
                            ->label('Total')
                            ->money('EUR'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Líneas')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('invoiceItems')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('description')
                                    ->label('Descripción'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label('Cantidad'),
                                Infolists\Components\TextEntry::make('unit_price')
                                    ->label('Precio unitario')
                                    ->money('EUR'),
                                Infolists\Components\TextEntry::make('discount_percent')
                                    ->label('Dto. %')
                                    ->suffix('%'),
                                Infolists\Components\TextEntry::make('line_total')
                                    ->label('Total línea')
                                    ->money('EUR'),
                            ])
                            ->columns(5),
                    ]),
            ]);
    }
}
