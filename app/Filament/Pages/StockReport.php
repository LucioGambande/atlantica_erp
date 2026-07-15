<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\StockMovementResource;
use App\Filament\Support\StatusBadge;
use App\Filament\Support\TableUi;
use App\Models\Product;
use App\Services\StockReportService;
use App\Support\ErpAuthorization;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class StockReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = NavigationGroups::INVENTARIO;

    protected static ?string $navigationLabel = 'Stock actual';

    protected static ?string $title = 'Reporte de stock';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.stock-report';

    protected static ?string $slug = 'stock-report';

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage stock');
    }

    public function getSummary(): array
    {
        return app(StockReportService::class)->summary();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->withSum(['stockMovements as stock_in' => fn (Builder $query) => $query->where('type', 'in')], 'quantity')
                    ->withSum(['stockMovements as stock_out' => fn (Builder $query) => $query->where('type', 'out')], 'quantity')
                    ->withMax('stockMovements as last_movement_at', 'created_at')
                    ->orderBy('name')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Producto')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Stock actual')
                    ->numeric()
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('stock_level', [
                        'zero' => 'Sin stock (0)',
                        'low' => 'Stock bajo (1–'.StockReportService::LOW_STOCK_THRESHOLD.')',
                        'ok' => 'Stock OK (>'.StockReportService::LOW_STOCK_THRESHOLD.')',
                    ]))
                    ->sortable()
                    ->alignEnd()
                    ->toggleable()
                    ->badge()
                    ->color(fn (Product $record): string => StatusBadge::stockLevel(
                        (int) $record->stock,
                        StockReportService::LOW_STOCK_THRESHOLD,
                    )),
                Tables\Columns\TextColumn::make('stock_in')
                    ->label('Entradas')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('0')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock_out')
                    ->label('Salidas')
                    ->numeric()
                    ->alignEnd()
                    ->placeholder('0')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('last_movement_at')
                    ->label('Último movimiento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sale_price')
                    ->label('PVP')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('stock_value')
                    ->label('Valor stock')
                    ->alignEnd()
                    ->state(fn (Product $record): float => (float) $record->stock * (float) $record->sale_price)
                    ->formatStateUsing(fn (float $state): string => Number::currency($state, 'EUR'))
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("(stock * sale_price) {$direction}");
                    }),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('stock_level')
                    ->label('Nivel de stock')
                    ->options([
                        'zero' => 'Sin stock (0)',
                        'low' => 'Stock bajo (1–'.StockReportService::LOW_STOCK_THRESHOLD.')',
                        'ok' => 'Stock OK (>'.StockReportService::LOW_STOCK_THRESHOLD.')',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'zero' => $query->where('stock', '<=', 0),
                            'low' => $query->whereBetween('stock', [1, StockReportService::LOW_STOCK_THRESHOLD]),
                            'ok' => $query->where('stock', '>', StockReportService::LOW_STOCK_THRESHOLD),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewProduct')
                    ->label('Producto')
                    ->icon('heroicon-o-cube')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record])),
                Tables\Actions\Action::make('viewMovements')
                    ->label('Movimientos')
                    ->icon('heroicon-o-arrows-right-left')
                    ->url(fn (Product $record): string => StockMovementResource::getUrl('index', [
                        'tableFilters' => [
                            'product_id' => ['value' => $record->id],
                        ],
                    ])),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(50)
            ->emptyStateHeading('Sin productos')
            ->striped();
    }
}
