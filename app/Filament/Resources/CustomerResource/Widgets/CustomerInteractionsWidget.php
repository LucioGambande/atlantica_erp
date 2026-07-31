<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Filament\Support\InteractionForm;
use App\Filament\Support\InteractionType;
use App\Models\Customer;
use App\Models\CustomerInteraction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerInteractionsWidget extends BaseWidget
{
    protected static ?string $heading = 'Acciones';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function getCustomer(): ?Customer
    {
        return $this->record instanceof Customer ? $this->record : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getCustomer()
                ? $this->getCustomer()->interactions()->getQuery()
                : CustomerInteraction::query()->whereRaw('1 = 0'))
            ->columns([
                Tables\Columns\TextColumn::make('next_action_type')
                    ->label('Próxima acción')
                    ->badge()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => InteractionType::options()[$state] ?? '—')
                    ->color(fn (?string $state): string => InteractionType::color($state)),
                Tables\Columns\TextColumn::make('next_action_at')
                    ->label('Fecha próxima acción')
                    ->date()
                    ->placeholder('—')
                    ->weight('bold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_action_notes')
                    ->label('Detalle próxima acción')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('happened_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InteractionType::options()[$state] ?? '—')
                    ->color(fn (?string $state): string => InteractionType::color($state)),
                Tables\Columns\TextColumn::make('notes')
                    ->label('Qué pasó')
                    ->wrap()
                    ->limit(150),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cargado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('happened_at', 'desc')
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva acción')
                    ->modalHeading('Cargar qué pasó y qué sigue')
                    ->form(InteractionForm::schema())
                    ->using(function (array $data): CustomerInteraction {
                        $data['user_id'] = auth()->id();
                        $data['happened_at'] = now();

                        return $this->getCustomer()->interactions()->create($data);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form(InteractionForm::schema()),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
