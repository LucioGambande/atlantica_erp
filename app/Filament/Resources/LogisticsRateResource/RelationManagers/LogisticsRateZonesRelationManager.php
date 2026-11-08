<?php

namespace App\Filament\Resources\LogisticsRateResource\RelationManagers;

use App\Models\LogisticsLocation;
use App\Models\LogisticsRateZone;
use App\Models\LogisticsRateZoneLocation;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogisticsRateZonesRelationManager extends RelationManager
{
    protected static string $relationship = 'zones';

    protected static ?string $title = 'Zonas';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('locations'))
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('locations_count')
                    ->label('Ubicaciones')
                    ->counts('locations')
                    ->sortable(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('editLocations')
                    ->label('Editar ubicaciones')
                    ->icon('heroicon-o-map-pin')
                    ->form([
                        Forms\Components\Select::make('location_ids')
                            ->label('Ubicaciones')
                            ->multiple()
                            ->searchable()
                            ->options(fn (): array => LogisticsLocation::query()->orderBy('name')->pluck('name', 'id')->all()),
                    ])
                    ->fillForm(fn (LogisticsRateZone $record): array => [
                        'location_ids' => $record->locations->pluck('id')->all(),
                    ])
                    ->action(function (LogisticsRateZone $record, array $data): void {
                        $locationIds = $data['location_ids'] ?? [];

                        $conflicting = LogisticsRateZoneLocation::query()
                            ->where('logistics_rate_id', $record->logistics_rate_id)
                            ->whereIn('logistics_location_id', $locationIds)
                            ->where('logistics_rate_zone_id', '!=', $record->id)
                            ->with('location')
                            ->get();

                        if ($conflicting->isNotEmpty()) {
                            Notification::make()
                                ->title('No se pudo guardar')
                                ->body('Ya asignadas a otra zona de esta tarifa: '.$conflicting->pluck('location.name')->implode(', '))
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->locations()->sync($locationIds, ['logistics_rate_id' => $record->logistics_rate_id]);
                    }),
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
