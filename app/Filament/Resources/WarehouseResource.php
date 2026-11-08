<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = NavigationGroups::LOGISTICA;

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'almacén';

    protected static ?string $pluralModelLabel = 'almacenes';

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
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos generales')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(Warehouse::TYPES)
                            ->live()
                            ->required(),
                        Forms\Components\Select::make('logistics_provider_id')
                            ->label('Proveedor logístico')
                            ->relationship('provider', 'name')
                            ->searchable()
                            ->preload()
                            ->required(fn (Get $get): bool => $get('type') !== 'own')
                            ->visible(fn (Get $get): bool => $get('type') !== 'own')
                            ->dehydrated(fn (Get $get): bool => $get('type') !== 'own'),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Textarea::make('address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => Warehouse::TYPES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider.name')
                    ->label('Proveedor')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Warehouse::TYPES),
                Tables\Filters\SelectFilter::make('logistics_provider_id')
                    ->label('Proveedor')
                    ->relationship('provider', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
