<?php

namespace App\Filament\Resources\PaymentResource\Pages;

use App\Filament\Resources\PaymentResource;
use App\Services\PaymentDetailService;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\EditAction::make(),
            \Filament\Actions\DeleteAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Pago')
                    ->schema([
                        Infolists\Components\TextEntry::make('customer.name')
                            ->label('Cliente'),
                        Infolists\Components\TextEntry::make('invoice.invoice_number')
                            ->label('Factura'),
                        Infolists\Components\TextEntry::make('amount')
                            ->label('Importe')
                            ->money('EUR'),
                        Infolists\Components\TextEntry::make('paymentMethod.name')
                            ->label('Forma de pago'),
                        Infolists\Components\TextEntry::make('paid_at')
                            ->label('Fecha de pago')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Detalle')
                    ->schema([
                        Infolists\Components\TextEntry::make('detail_summary')
                            ->label('Información')
                            ->state(fn ($record): string => app(PaymentDetailService::class)->summary($record->detail)),
                    ]),
            ]);
    }
}
