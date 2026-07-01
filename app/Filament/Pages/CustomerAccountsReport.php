<?php

namespace App\Filament\Pages;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\CustomerResource;
use App\Filament\Support\TableUi;
use App\Models\Customer;
use App\Support\ErpAuthorization;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerAccountsReport extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = NavigationGroups::CLIENTES;

    protected static ?string $navigationLabel = 'Cuentas corrientes';

    protected static ?string $title = 'Cuentas corrientes';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.customer-accounts-report';

    protected static ?string $slug = 'customer-accounts';

    public static function canAccess(): bool
    {
        return ErpAuthorization::userCan('manage customers');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Customer::query()
                    ->orderByDesc('balance')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Cliente')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('EUR')
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('balance_status', [
                        'debt' => 'Con deuda',
                        'credit' => 'Saldo a favor',
                        'zero' => 'Saldo cero',
                    ]))
                    ->sortable()
                    ->toggleable()
                    ->color(fn (Customer $record): string => (float) $record->balance > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('credit_limit')
                    ->label('Límite crédito')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('tax_id')
                    ->label('NIF')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('phone')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city')
                    ->label('Ciudad')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('balance_status')
                    ->label('Saldo')
                    ->options([
                        'debt' => 'Con deuda',
                        'credit' => 'Saldo a favor',
                        'zero' => 'Saldo cero',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'debt' => $query->where('balance', '>', 0),
                            'credit' => $query->where('balance', '<', 0),
                            'zero' => $query->where('balance', '=', 0),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('statement')
                    ->label('Ver cuenta')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('statement', ['record' => $record->getKey()])),
                Tables\Actions\Action::make('edit')
                    ->label('Cliente')
                    ->icon('heroicon-o-user')
                    ->url(fn (Customer $record): string => CustomerResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('balance', 'desc')
            ->striped();
    }
}
