<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\CustomerResource;
use App\Filament\Support\InteractionForm;
use App\Filament\Support\InteractionType;
use App\Models\Customer;
use App\Support\ErpAuthorization;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class UpcomingActions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = NavigationGroups::CLIENTES;

    protected static ?string $navigationLabel = 'Próximas acciones';

    protected static ?string $title = 'Próximas acciones';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.upcoming-actions';

    protected static ?string $slug = 'proximas-acciones';

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage customers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->withoutGlobalScopes([SoftDeletingScope::class])
                    ->whereHas('latestInteraction', fn (Builder $query): Builder => $query->whereNotNull('next_action_at'))
                    ->with('latestInteraction')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('latestInteraction.next_action_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn (Customer $record): ?string => match (true) {
                        $record->latestInteraction?->next_action_at?->isToday() => 'warning',
                        $record->latestInteraction?->next_action_at?->isPast() => 'danger',
                        default => null,
                    }),
                Tables\Columns\TextColumn::make('latestInteraction.next_action_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => InteractionType::options()[$state] ?? '—')
                    ->color(fn (?string $state): string => InteractionType::color($state)),
                Tables\Columns\TextColumn::make('latestInteraction.next_action_notes')
                    ->label('Detalle')
                    ->placeholder('—')
                    ->wrap(),
                Tables\Columns\TextColumn::make('latestInteraction.notes')
                    ->label('Última acción')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->toggleable(),
            ])
            ->defaultSort('latestInteraction.next_action_at')
            ->actions([
                Tables\Actions\Action::make('logAction')
                    ->label('Registrar')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading(fn (Customer $record): string => 'Registrar acción — '.$record->name)
                    ->form(InteractionForm::schema())
                    ->action(function (Customer $record, array $data): void {
                        $record->interactions()->create($data + [
                            'user_id' => auth()->id(),
                            'happened_at' => now(),
                        ]);
                    }),
                Tables\Actions\Action::make('viewCustomer')
                    ->label('Cliente')
                    ->icon('heroicon-o-user')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record])),
            ])
            ->paginated([25, 50, 100])
            ->emptyStateHeading('Sin acciones pendientes')
            ->striped();
    }
}
