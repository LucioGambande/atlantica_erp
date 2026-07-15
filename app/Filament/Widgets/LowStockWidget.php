<?php

namespace App\Filament\Widgets;

use App\Filament\Support\StatusBadge;
use App\Filament\Pages\StockReport;
use App\Filament\Resources\ProductResource;
use App\Models\Product;
use App\Services\StockReportService;
use App\Support\ErpAuthorization;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LowStockWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return ErpAuthorization::userCan('manage stock')
            || ErpAuthorization::userCan('manage products');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Stock bajo')
            ->description('Productos con stock ≤ '.StockReportService::LOW_STOCK_THRESHOLD.' unidades')
            ->query(
                Product::query()
                    ->where('stock', '<=', StockReportService::LOW_STOCK_THRESHOLD)
                    ->orderBy('stock')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn (Product $record): string => StatusBadge::stockLevel(
                        (int) $record->stock,
                        StockReportService::LOW_STOCK_THRESHOLD,
                    )),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('PVP')
                    ->money('EUR')
                    ->sortable(),
            ])
            ->defaultSort('stock')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('Stock en niveles normales')
            ->emptyStateDescription('Ningún producto por debajo del umbral configurado.')
            ->headerActions([
                Tables\Actions\Action::make('viewReport')
                    ->label('Ver reporte completo')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->url(StockReport::getUrl()),
            ]);
    }
}
