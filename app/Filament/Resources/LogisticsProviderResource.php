<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\LogisticsProviderResource\Pages;
use App\Models\LogisticsProvider;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LogisticsProviderResource extends Resource
{
    protected static ?string $model = LogisticsProvider::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = NavigationGroups::LOGISTICA;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'proveedor logístico';

    protected static ?string $pluralModelLabel = 'proveedores logísticos';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage logistics');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'tax_id', 'phone'];
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
                            ->options(LogisticsProvider::TYPES)
                            ->required(),
                        Forms\Components\TextInput::make('tax_id')
                            ->label('CUIT / Tax ID')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                        Forms\Components\Textarea::make('address')
                            ->label('Dirección')
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('notes')
                            ->label('Observaciones')
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
                    ->formatStateUsing(fn (string $state): string => LogisticsProvider::TYPES[$state] ?? $state)
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tax_id')
                    ->label('CUIT / Tax ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('rates_count')
                    ->label('Tarifas')
                    ->counts('rates')
                    ->sortable(),
                Tables\Columns\TextColumn::make('warehouses_count')
                    ->label('Almacenes')
                    ->counts('warehouses')
                    ->sortable(),
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
                    ->options(LogisticsProvider::TYPES),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Activo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make()
                    ->visible(fn (LogisticsProvider $record): bool => $record->rates()->count() === 0 && $record->warehouses()->count() === 0),
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
            'index' => Pages\ListLogisticsProviders::route('/'),
            'create' => Pages\CreateLogisticsProvider::route('/create'),
            'edit' => Pages\EditLogisticsProvider::route('/{record}/edit'),
        ];
    }
}
