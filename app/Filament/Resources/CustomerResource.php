<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Support\TableUi;
use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Customer;
use App\Models\PriceList;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = NavigationGroups::CLIENTES;

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'cliente';

    protected static ?string $pluralModelLabel = 'clientes';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage customers');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'tax_id', 'phone'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with('priceList');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre / razón social')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('price_list_id')
                    ->label('Lista de precios')
                    ->options(fn (): array => PriceList::query()->active()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->placeholder('Sin lista asignada — se usará la lista default')
                    ->helperText('Al crear facturas y pedidos, los precios se precargarán desde esta lista.'),
                Forms\Components\TextInput::make('tax_id')
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->tel()
                    ->maxLength(255),
                Forms\Components\Textarea::make('address')
                    ->columnSpanFull(),
                Forms\Components\Select::make('customer_type')
                    ->options([
                        'horeca' => 'Horeca',
                        'individual' => 'Individual',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('credit_limit')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TableUi::customerLink(
                    Tables\Columns\TextColumn::make('name')
                        ->searchable(isIndividual: true, isGlobal: false)
                        ->sortable()
                        ->toggleable(),
                ),
                Tables\Columns\TextColumn::make('priceList.name')
                    ->label('Lista de precios')
                    ->placeholder('Default')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('balance')
                    ->label('Saldo')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable()
                    ->color(fn (Customer $record): string => (float) $record->balance > 0 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('tax_id')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('customer_type')
                    ->badge()
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('customer_type', [
                        'horeca' => 'Horeca',
                        'individual' => 'Individual',
                    ]))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('credit_limit')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('customer_type')
                    ->options([
                        'horeca' => 'Horeca',
                        'individual' => 'Individual',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q, $v) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('statement')
                    ->label('Cuenta corriente')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Customer $record): string => static::getUrl('statement', ['record' => $record->getKey()])),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
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
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
            'statement' => Pages\CustomerStatement::route('/{record}/statement'),
        ];
    }
}
