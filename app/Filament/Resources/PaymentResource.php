<?php

namespace App\Filament\Resources;

use App\Filament\Forms\PaymentAllocationForm;
use App\Filament\Forms\PaymentDetailForm;
use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\PaymentResource\Pages;
use App\Filament\Support\TableUi;
use App\Models\Payment;
use App\Services\PaymentDetailService;
use App\Support\ErpAuthorization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Set;
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer', 'paymentMethod', 'detail', 'allocations.invoice'])
            ->withSum('allocations as allocations_sum_amount', 'amount');
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
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        PaymentAllocationForm::resetAllocationFields($set);
                    }),
                ...PaymentAllocationForm::allocationFields(),
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
                TableUi::customerLink(
                    Tables\Columns\TextColumn::make('customer.name')
                        ->label('Cliente')
                        ->searchable()
                        ->sortable()
                        ->toggleable(),
                ),
                Tables\Columns\TextColumn::make('allocations_summary')
                    ->label('Imputado a')
                    ->state(function (Payment $record): string {
                        $record->loadMissing('allocations.invoice');

                        if ($record->allocations->isEmpty()) {
                            return $record->unallocatedAmount() > 0 ? 'Anticipo sin asignar' : '—';
                        }

                        return $record->allocations
                            ->map(function ($allocation): string {
                                $invoice = $allocation->invoice;

                                if ($invoice === null) {
                                    return 'Factura: '.number_format((float) $allocation->amount, 2, ',', '.').' €';
                                }

                                return $invoice->invoice_number.': '.number_format((float) $allocation->amount, 2, ',', '.').' €';
                            })
                            ->implode(' · ');
                    })
                    ->wrap()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Cobro')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('unallocated_amount')
                    ->label('Sin asignar')
                    ->state(fn (Payment $record): float => $record->unallocatedAmount())
                    ->money('EUR')
                    ->color(fn (Payment $record): string => $record->unallocatedAmount() > 0 ? 'warning' : 'success')
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
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\Filter::make('paid_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Pagado desde'),
                        Forms\Components\DatePicker::make('until')->label('Pagado hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'] ?? null, fn ($q) => $q->whereDate('paid_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn ($q) => $q->whereDate('paid_at', '<=', $data['until']));
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
