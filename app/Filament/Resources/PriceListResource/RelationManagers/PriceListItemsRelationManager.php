<?php

namespace App\Filament\Resources\PriceListResource\RelationManagers;

use App\Models\PriceListItem;
use App\Models\Product;
use App\Support\VatTotals;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class PriceListItemsRelationManager extends RelationManager
{
    protected static ?string $title = 'Precios por producto';

    protected static string $relationship = 'items';

    public function table(Table $table): Table
    {
        $priceListId = $this->getOwnerRecord()->getKey();
        $listDiscount = (float) $this->getOwnerRecord()->discount_percent;

        return $table
            ->query(
                Product::query()
                    ->orderBy('name')
                    ->with(['priceListItems' => fn ($query) => $query->where('price_list_id', $priceListId)])
            )
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->description(fn (Product $record): string => $record->sku)
                    ->searchable(['name', 'sku'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Precio base')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextInputColumn::make('price')
                    ->label('Precio (sin IVA)')
                    ->type('number')
                    ->step(0.01)
                    ->rules(['required', 'numeric', 'min:0'])
                    ->state(fn (Product $record): string => (string) ($record->priceListItems->first()->price ?? $record->sale_price))
                    ->updateStateUsing(fn (Product $record, $state): string => (string) $this->upsertPrice(
                        $priceListId,
                        $record,
                        price: max(0, (float) $state),
                    )),
                Tables\Columns\TextInputColumn::make('price_with_vat')
                    ->label('Precio (con IVA)')
                    ->type('number')
                    ->step(0.01)
                    ->rules(['numeric', 'min:0'])
                    ->state(fn (Product $record): string => (string) VatTotals::grossFromNet(
                        (float) ($record->priceListItems->first()->price ?? $record->sale_price)
                    ))
                    ->updateStateUsing(fn (Product $record, $state): string => (string) VatTotals::grossFromNet($this->upsertPrice(
                        $priceListId,
                        $record,
                        price: VatTotals::netFromGross(max(0, (float) $state)),
                    ))),
                Tables\Columns\TextInputColumn::make('discount_percent')
                    ->label('Dto. adicional (%)')
                    ->type('number')
                    ->step(0.01)
                    ->rules(['numeric', 'min:0', 'max:100'])
                    ->state(fn (Product $record): string => (string) ($record->priceListItems->first()->discount_percent ?? 0))
                    ->updateStateUsing(function (Product $record, $state) use ($priceListId): string {
                        $discount = max(0, min(100, (float) $state));

                        $item = PriceListItem::query()->firstOrNew([
                            'price_list_id' => $priceListId,
                            'product_id' => $record->id,
                        ]);

                        if (! $item->exists) {
                            $item->price = $record->sale_price;
                        }

                        $item->discount_percent = $discount;
                        $item->save();

                        return (string) $discount;
                    }),
                Tables\Columns\TextColumn::make('final_price')
                    ->label('Final (sin IVA)')
                    ->money('EUR')
                    ->state(function (Product $record) use ($listDiscount): float {
                        $item = $record->priceListItems->first();

                        return $item !== null
                            ? $item->final_price
                            : round((float) $record->sale_price * (1 - $listDiscount / 100), 2);
                    }),
                Tables\Columns\TextColumn::make('final_price_with_vat')
                    ->label('Final (con IVA)')
                    ->money('EUR')
                    ->state(function (Product $record) use ($listDiscount): float {
                        $item = $record->priceListItems->first();
                        $final = $item !== null
                            ? $item->final_price
                            : round((float) $record->sale_price * (1 - $listDiscount / 100), 2);

                        return VatTotals::grossFromNet($final);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('reset_price')
                    ->label('Quitar precio especial')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (Product $record): bool => $record->priceListItems->isNotEmpty())
                    ->requiresConfirmation()
                    ->modalDescription('El producto va a volver a usar el precio base de esta lista (PVP menos el descuento global).')
                    ->action(function (Product $record) use ($priceListId): void {
                        PriceListItem::query()
                            ->where('price_list_id', $priceListId)
                            ->where('product_id', $record->id)
                            ->delete();
                    }),
            ])
            ->paginated([25, 50, 100, 'all']);
    }

    private function upsertPrice(int $priceListId, Product $record, float $price): float
    {
        $item = PriceListItem::query()->firstOrNew([
            'price_list_id' => $priceListId,
            'product_id' => $record->id,
        ]);

        $item->price = $price;
        $item->discount_percent ??= 0;
        $item->save();

        return $price;
    }
}
