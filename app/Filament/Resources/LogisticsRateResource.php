<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\LogisticsRateResource\Pages;
use App\Filament\Resources\LogisticsRateResource\RelationManagers;
use App\Models\LogisticsRate;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LogisticsRateResource extends Resource
{
    protected static ?string $model = LogisticsRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationGroup = NavigationGroups::LOGISTICA;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'tarifa logística';

    protected static ?string $pluralModelLabel = 'tarifas logísticas';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage logistics');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with([
                'provider',
                'originLocation',
                'destinationLocation',
                'versions' => fn (HasMany $query) => $query->current()->orderByDesc('valid_from'),
            ]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la tarifa')
                    ->schema([
                        Forms\Components\Select::make('logistics_provider_id')
                            ->label('Proveedor')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('service_type')
                            ->label('Tipo de servicio')
                            ->options(LogisticsRate::SERVICE_TYPES)
                            ->required(),
                        Forms\Components\Select::make('unit_of_measure')
                            ->label('Unidad de cálculo')
                            ->options(LogisticsRate::UNITS)
                            ->required(),
                        Forms\Components\Select::make('pricing_type')
                            ->label('Tipo de precio')
                            ->options(LogisticsRate::PRICING_TYPES)
                            ->default('flat')
                            ->live()
                            ->helperText('Matriz: precio según tramo de peso/volumen y zona de destino (ej. tarifas de transportistas nacionales).')
                            ->required(),
                        Forms\Components\Select::make('origin_location_id')
                            ->label('Origen')
                            ->relationship('originLocation', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('pricing_type') === 'flat'),
                        Forms\Components\Select::make('destination_location_id')
                            ->label('Destino')
                            ->relationship('destinationLocation', 'name')
                            ->searchable()
                            ->preload()
                            ->visible(fn (Forms\Get $get): bool => $get('pricing_type') === 'flat'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activa')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('service_type')
                    ->label('Servicio')
                    ->formatStateUsing(fn (string $state): string => LogisticsRate::SERVICE_TYPES[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('route')
                    ->label('Ruta')
                    ->state(fn (LogisticsRate $record): string => $record->originLocation || $record->destinationLocation
                        ? trim(($record->originLocation?->name).' → '.($record->destinationLocation?->name), ' →')
                        : '—'),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('Unidad')
                    ->formatStateUsing(fn (string $state): string => LogisticsRate::UNITS[$state] ?? $state),
                Tables\Columns\TextColumn::make('pricing_type')
                    ->label('Tipo de precio')
                    ->formatStateUsing(fn (string $state): string => LogisticsRate::PRICING_TYPES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => $state === 'matrix' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('current_price')
                    ->label('Precio vigente')
                    ->state(fn (LogisticsRate $record): ?string => $record->currentVersion()?->price !== null
                        ? number_format((float) $record->currentVersion()->price, 2).' '.$record->currentVersion()->currency
                        : '—'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('logistics_provider_id')
                    ->label('Proveedor')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('service_type')
                    ->label('Tipo de servicio')
                    ->options(LogisticsRate::SERVICE_TYPES),
                Tables\Filters\Filter::make('currently_valid')
                    ->label('Vigente hoy')
                    ->query(fn (Builder $query): Builder => $query->whereHas(
                        'versions',
                        fn (Builder $query) => $query->current()
                    )),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn (LogisticsRate $record): bool => $record->versions()->count() === 0),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\LogisticsRateZonesRelationManager::class,
            RelationManagers\LogisticsRateVersionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLogisticsRates::route('/'),
            'create' => Pages\CreateLogisticsRate::route('/create'),
            'edit' => Pages\EditLogisticsRate::route('/{record}/edit'),
        ];
    }
}
