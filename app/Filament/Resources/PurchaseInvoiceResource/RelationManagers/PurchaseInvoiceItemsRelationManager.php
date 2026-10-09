<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;

use App\Filament\Support\LotField;
use App\Services\StockService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PurchaseInvoiceItemsRelationManager extends RelationManager
{
    protected static ?string $title = 'Líneas de compra';

    protected static string $relationship = 'purchaseInvoiceItems';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live(),
                LotField::make(),
                Forms\Components\TextInput::make('description')
                    ->maxLength(255),
                Forms\Components\TextInput::make('quantity')
                    ->required()
                    ->integer()
                    ->minValue(1),
                Forms\Components\TextInput::make('unit_price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\TextInput::make('total_price')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->description(fn (): ?string => $this->stockIsLocked()
                ? 'La mercadería de esta compra ya ingresó al stock. Para modificar las líneas, volvé la compra a borrador.'
                : null)
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('lot.code')
                    ->label('Lote')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->money('EUR')
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
     * Una vez que la mercadería entró al depósito, las líneas quedan
     * congeladas: editarlas dejaría el stock registrado sin respaldo.
     */
    private function stockIsLocked(): bool
    {
        return (bool) $this->getOwnerRecord()->stock_movements_recorded;
    }

    /**
     * Cubre el caso de cargar las líneas con la compra ya marcada como
     * recibida: el stock entra apenas hay mercadería que registrar.
     */
    private function syncStock(): void
    {
        app(StockService::class)->syncStockForPurchaseInvoice($this->getOwnerRecord());
    }
}
