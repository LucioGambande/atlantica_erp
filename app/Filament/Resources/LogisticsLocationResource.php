<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\LogisticsLocationResource\Pages;
use App\Models\LogisticsLocation;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class LogisticsLocationResource extends Resource
{
    protected static ?string $model = LogisticsLocation::class;

    protected static ?string $navigationIcon = 'heroicon-o-map';

    protected static ?string $navigationGroup = NavigationGroups::LOGISTICA;

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'ubicación';

    protected static ?string $pluralModelLabel = 'ubicaciones';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage logistics');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('country')
                    ->label('País')
                    ->options(['ES' => 'España', 'PT' => 'Portugal'])
                    ->required(),
                Forms\Components\Select::make('category')
                    ->label('Categoría')
                    ->options(LogisticsLocation::CATEGORIES)
                    ->live()
                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set(
                        'volumetric_kg_per_m3',
                        $state === 'peninsula' ? 270 : 333,
                    ))
                    ->required(),
                Forms\Components\TextInput::make('volumetric_kg_per_m3')
                    ->label('Factor volumétrico (kg/m³)')
                    ->numeric()
                    ->minValue(0)
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('country')
                    ->label('País')
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->label('Categoría')
                    ->formatStateUsing(fn (string $state): string => LogisticsLocation::CATEGORIES[$state] ?? $state)
                    ->badge(),
                Tables\Columns\TextColumn::make('volumetric_kg_per_m3')
                    ->label('kg/m³')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoría')
                    ->options(LogisticsLocation::CATEGORIES),
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
            'index' => Pages\ListLogisticsLocations::route('/'),
            'create' => Pages\CreateLogisticsLocation::route('/create'),
            'edit' => Pages\EditLogisticsLocation::route('/{record}/edit'),
        ];
    }
}
