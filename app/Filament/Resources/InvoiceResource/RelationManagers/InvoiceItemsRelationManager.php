<?php

namespace App\Filament\Resources\InvoiceResource\RelationManagers;

use App\Filament\Resources\OrderResource;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceItemsRelationManager extends RelationManager
{
    protected static ?string $title = 'Líneas de factura';

    protected static string $relationship = 'invoiceItems';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                        if (! $state) {
                            return;
                        }
                        $product = Product::query()->find($state);
                        if ($product) {
                            $set('unit_price', (float) $product->sale_price);
                            if (blank($get('description'))) {
                                $set('description', $product->name);
                            }
                        }
                        OrderResource::recalculateLineTotal($set, $get);
                    }),
                Forms\Components\Textarea::make('description')
                    ->label('Descripción')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Cantidad')
                    ->required()
                    ->integer()
                    ->minValue(1)
                    ->default(1)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        OrderResource::recalculateLineTotal($set, $get);
                    }),
                Forms\Components\TextInput::make('unit_price')
                    ->label('P. unit.')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('€')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        OrderResource::recalculateLineTotal($set, $get);
                    }),
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Dto. (%)')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, Get $get): void {
                        OrderResource::recalculateLineTotal($set, $get);
                    }),
                Forms\Components\TextInput::make('total_price')
                    ->label('Subtotal')
                    ->numeric()
                    ->readOnly()
                    ->prefix('€')
                    ->dehydrated()
                    ->default(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('unit_price')
                    ->label('P. unit.')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Dto. (%)')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Subtotal')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->after(function (): void {
                        $this->getOwnerRecord()->recalculateTotalFromItems();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->after(function (): void {
                        $this->getOwnerRecord()->recalculateTotalFromItems();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->after(function (): void {
                        $this->getOwnerRecord()->recalculateTotalFromItems();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->after(function (): void {
                            $this->getOwnerRecord()->recalculateTotalFromItems();
                        }),
                ]),
            ]);
    }
}
