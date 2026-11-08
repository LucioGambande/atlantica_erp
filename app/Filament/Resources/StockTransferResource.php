<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\StockTransferResource\Pages;
use App\Filament\Resources\StockTransferResource\RelationManagers;
use App\Filament\Support\StatusBadge;
use App\Filament\Support\TableUi;
use App\Models\StockTransfer;
use App\Services\StockService;
use App\Support\ErpAuthorization;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class StockTransferResource extends Resource
{
    protected static ?string $model = StockTransfer::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = NavigationGroups::INVENTARIO;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'traslado de stock';

    protected static ?string $pluralModelLabel = 'traslados de stock';

    protected static ?string $recordTitleAttribute = 'id';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage stock');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['originWarehouse', 'destinationWarehouse']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('origin_warehouse_id')
                    ->label('Almacén de origen')
                    ->relationship('originWarehouse', 'name', fn (Builder $query) => $query->active())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live(),
                Forms\Components\Select::make('destination_warehouse_id')
                    ->label('Almacén de destino')
                    ->relationship('destinationWarehouse', 'name', fn (Builder $query) => $query->active())
                    ->required()
                    ->searchable()
                    ->preload()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                            if (filled($value) && (int) $value === (int) $get('origin_warehouse_id')) {
                                $fail('El almacén de destino debe ser distinto al de origen.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options(StockTransfer::STATUSES)
                    ->required()
                    ->default('draft')
                    ->disabled(fn (?StockTransfer $record): bool => $record?->stock_movements_recorded ?? false),
                Forms\Components\Placeholder::make('stock_movements_recorded_notice')
                    ->label('Stock')
                    ->content('Este traslado ya movió stock. Para corregir las líneas o el estado, volvelo a borrador.')
                    ->visible(fn (?StockTransfer $record): bool => $record?->stock_movements_recorded ?? false),
                Forms\Components\DateTimePicker::make('transferred_at')
                    ->label('Fecha del traslado')
                    ->nullable(),
                Forms\Components\Textarea::make('notes')
                    ->label('Notas')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('originWarehouse.name')
                    ->label('Origen')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('destinationWarehouse.name')
                    ->label('Destino')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StatusBadge::stockTransferLabel($state))
                    ->color(fn (string $state): string => StatusBadge::stockTransfer($state))
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('status', StockTransfer::STATUSES))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('transferred_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('origin_warehouse_id')
                    ->label('Origen')
                    ->relationship('originWarehouse', 'name'),
                Tables\Filters\SelectFilter::make('destination_warehouse_id')
                    ->label('Destino')
                    ->relationship('destinationWarehouse', 'name'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(StockTransfer::STATUSES),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(fn (StockTransfer $record) => static::releaseStock($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(fn (Collection $records) => $records->each(
                            fn (StockTransfer $record) => static::releaseStock($record),
                        )),
                ]),
            ]);
    }

    /**
     * Borrar un traslado ya completado tiene que devolver el stock que
     * movió: si no, quedaría en el almacén de destino sin respaldo
     * documental.
     */
    public static function releaseStock(StockTransfer $transfer): void
    {
        app(StockService::class)->reverseStockTransfer($transfer);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\StockTransferItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockTransfers::route('/'),
            'create' => Pages\CreateStockTransfer::route('/create'),
            'edit' => Pages\EditStockTransfer::route('/{record}/edit'),
        ];
    }
}
