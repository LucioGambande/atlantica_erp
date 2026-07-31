<?php

namespace App\Filament\Resources\CustomerResource\RelationManagers;

use App\Filament\Support\InteractionForm;
use App\Filament\Support\InteractionType;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class InteractionsRelationManager extends RelationManager
{
    protected static string $relationship = 'interactions';

    protected static ?string $title = 'Historial';

    public function form(Form $form): Form
    {
        return $form->schema(InteractionForm::schema());
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('notes')
            ->columns([
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
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('next_action_notes')
                    ->label('Detalle')
                    ->placeholder('—')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Cargado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('happened_at', 'desc')
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Nueva acción')
                    ->modalHeading('Cargar qué pasó y qué sigue')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = auth()->id();
                        $data['happened_at'] = now();

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([]);
    }
}
