<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PaymentDetailForm;
use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentDetailService;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = NavigationGroups::FACTURACION;

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'pago';

    protected static ?string $pluralModelLabel = 'pagos';

    protected static ?string $recordTitleAttribute = 'id';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage invoices');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return [];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('invoice_id')
                    ->label('Factura')
                    ->relationship(
                        name: 'invoice',
                        titleAttribute: 'invoice_number',
                        modifyQueryUsing: fn (Builder $query) => $query->with('customer'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Invoice $record): string => $record->invoice_number.' — '.($record->customer?->name ?? '')
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('amount')
                    ->label('Importe')
                    ->required()
                    ->numeric()
                    ->minValue(0.01)
                    ->step(0.01),
                PaymentDetailForm::methodSelect(),
                PaymentDetailForm::detailsSection(),
                Forms\Components\DateTimePicker::make('paid_at')
                    ->label('Fecha de pago')
                    ->required()
                    ->default(now()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Factura')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Forma de pago')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('detail_summary')
                    ->label('Detalle')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->state(fn (Payment $record): string => app(PaymentDetailService::class)->summary($record->detail)),
                Tables\Columns\TextColumn::make('paid_at')
                    ->label('Fecha de pago')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Pagado desde'),
                        Forms\Components\DatePicker::make('until')->label('Pagado hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q) => $q->whereDate('paid_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q) => $q->whereDate('paid_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
            'index' => Pages\ListPayments::route('/'),
            'create' => Pages\CreatePayment::route('/create'),
            'view' => Pages\ViewPayment::route('/{record}'),
            'edit' => Pages\EditPayment::route('/{record}/edit'),
        ];
    }

    public static function canEdit($record): bool
    {
        return ErpAuthorization::userCan('manage invoices');
    }
}
