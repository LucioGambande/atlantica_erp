<?php

namespace App\Filament\Resources\StockTransferResource\RelationManagers;

use App\Filament\Support\LotField;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class StockTransferItemsRelationManager extends RelationManager
{
    protected static ?string $title = 'Líneas del traslado';

    protected static string $relationship = 'stockTransferItems';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                LotField::make(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->required()
                    ->integer()
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->description(fn (): ?string => $this->stockIsLocked()
                ? 'Este traslado ya movió stock. Para modificar las líneas, volvé el traslado a borrador.'
                : null)
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lot.code')
                    ->label('Lote')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->visible(fn (): bool => ! $this->stockIsLocked())
                    ->after(function (): void {
                        $this->syncStock();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => ! $this->stockIsLocked()),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn (): bool => ! $this->stockIsLocked()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn (): bool => ! $this->stockIsLocked()),
                ]),
            ]);
    }

    /**
     * Una vez que el traslado movió stock, las líneas quedan congeladas:
     * editarlas dejaría el stock registrado sin respaldo.
     */
    private function stockIsLocked(): bool
    {
        return (bool) $this->getOwnerRecord()->stock_movements_recorded;
    }

    /**
     * Cubre el caso de cargar las líneas con el traslado ya marcado como
     * completado: el stock se mueve apenas hay líneas que registrar.
     */
    private function syncStock(): void
    {
        app(StockService::class)->syncStockForTransfer($this->getOwnerRecord());
    }
}
