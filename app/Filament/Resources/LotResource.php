<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\LotResource\Pages;
use App\Models\Lot;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LotResource extends Resource
{
    protected static ?string $model = Lot::class;

    protected static ?string $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationGroup = NavigationGroups::INVENTARIO;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'lote';

    protected static ?string $pluralModelLabel = 'lotes';

    protected static ?string $recordTitleAttribute = 'code';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage products');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code'];
    }

    public static function getEloquentQuery(): Builder
    {
        // El select('lots.*') va primero: llamarlo después de withCount()
        // reinicia las columnas y descarta sus subconsultas.
        return parent::getEloquentQuery()
            ->select('lots.*')
            ->with('product')
            ->withCount(['purchaseInvoiceItems', 'invoiceItems'])
            // Subconsulta en vez de acumulador: el saldo sale siempre del log
            // de movimientos, así no puede desincronizarse.
            ->addSelect(['stock_balance' => StockMovement::query()
                ->selectRaw("COALESCE(SUM(CASE WHEN type = 'in' THEN quantity ELSE -quantity END), 0)")
                ->whereColumn('stock_movements.lot_id', 'lots.id'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Producto')
                    ->options(fn (): array => Product::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Product $product): array => [
                            $product->id => $product->name.' · '.$product->sku,
                        ])
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('code')
                    ->label('Código de lote')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Único por producto.'),
                Forms\Components\DatePicker::make('manufactured_at')
                    ->label('Fecha de fabricación')
                    ->native(false),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Lote')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('product.name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufactured_at')
                    ->label('Fabricación')
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_balance')
                    ->label('En stock')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'success' : 'gray')
                    ->suffix(' u.'),
                Tables\Columns\TextColumn::make('purchase_invoice_items_count')
                    ->label('Líneas de compra')
                    ->numeric(),
                Tables\Columns\TextColumn::make('invoice_items_count')
                    ->label('Líneas de venta')
                    ->numeric(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('product_id')
                    ->label('Producto')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLots::route('/'),
            'create' => Pages\CreateLot::route('/create'),
            'edit' => Pages\EditLot::route('/{record}/edit'),
        ];
    }
}
