<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Services\PaymentDetailService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static ?string $title = 'Pagos';

    protected static string $relationship = 'payments';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('amount')
                    ->label('Importe')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Forma de pago')
                    ->sortable(),
                Tables\Columns\TextColumn::make('detail_summary')
                    ->label('Detalle')
                    ->state(fn ($record): string => app(PaymentDetailService::class)->summary($record->detail)),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Fecha de pago')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
