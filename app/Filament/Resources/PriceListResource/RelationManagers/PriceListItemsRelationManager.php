<?php

namespace App\Filament\Resources\PriceListResource\RelationManagers;

use App\Models\PriceListItem;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Number;
use Illuminate\Validation\Rules\Unique;

class PriceListItemsRelationManager extends RelationManager
{
    protected static ?string $title = 'Precios por producto';

    protected static string $relationship = 'items';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Producto')
                    ->relationship(
                        name: 'product',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn ($query) => $query->orderBy('name'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Product $record): string => $record->name.' · '.$record->sku
                    )
                    ->searchable(['name', 'sku'])
                    ->preload()
                    ->required()
                    ->unique(
                        table: 'price_list_items',
                        column: 'product_id',
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where(
                            'price_list_id',
                            $this->getOwnerRecord()->getKey(),
                        ),
                    ),
                Forms\Components\TextInput::make('price')
                    ->label('Precio')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->prefix('€')
                    ->live(onBlur: true),
                Forms\Components\TextInput::make('discount_percent')
                    ->label('Dto. adicional')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->maxValue(100)
                    ->suffix('%')
                    ->live(onBlur: true),
                Forms\Components\Placeholder::make('final_price_preview')
                    ->label('Precio final')
                    ->content(function (Get $get): string {
                        $price = (float) ($get('price') ?? 0);
                        $discount = (float) ($get('discount_percent') ?? 0);
                        $final = round($price * (1 - $discount / 100), 2);

                        return Number::currency($final, 'EUR');
                    }),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->description(fn (PriceListItem $record): string => $record->product?->sku ?? '')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('price')
                    ->label('Precio')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount_percent')
                    ->label('Dto. (%)')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('Precio final')
                    ->money('EUR')
                    ->state(fn (PriceListItem $record): float => $record->final_price),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
