<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Filament\Forms\PaymentDetailForm;
use App\Models\Invoice;
use App\Models\Order;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use InvalidArgumentException;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'ERP';

    protected static ?string $modelLabel = 'factura';

    protected static ?string $pluralModelLabel = 'facturas';

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number'];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function markAsPaidFormSchema(Invoice $invoice): array
    {
        return [
            Forms\Components\Placeholder::make('amount_preview')
                ->label('Importe a registrar')
                ->content(Number::currency($invoice->remainingAmount(), 'EUR')),
            PaymentDetailForm::methodSelect(),
            PaymentDetailForm::detailsSection(),
            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Fecha de pago')
                ->required()
                ->default(now()),
        ];
    }

    public static function registerInvoicePayment(Invoice $invoice, array $data): void
    {
        try {
            app(PaymentService::class)->registerInvoicePayment(
                $invoice,
                (int) $data['payment_method_id'],
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
                isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
            );

            Notification::make()
                ->title('Pago registrado')
                ->body('La factura quedó marcada como pagada.')
                ->success()
                ->send();
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title('No se pudo registrar el pago')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
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
                Forms\Components\Select::make('order_id')
                    ->label('Pedido')
                    ->relationship(
                        name: 'order',
                        titleAttribute: 'id',
                        modifyQueryUsing: fn (Builder $query) => $query->with('customer'),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn (Order $record): string => '#'.$record->id.' — '.($record->customer?->name ?? '')
                    )
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Forms\Components\TextInput::make('invoice_number')
                    ->label('Número de factura')
                    ->required()
                    ->maxLength(255)
                    ->unique(column: 'invoice_number', ignoreRecord: true),
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                    ])
                    ->required()
                    ->default('draft')
                    ->disabled(fn (?Invoice $record): bool => $record?->status === 'paid'),
                Forms\Components\Placeholder::make('paid_status')
                    ->label('Estado')
                    ->content('Pagada')
                    ->visible(fn (?Invoice $record): bool => $record?->status === 'paid'),
                Forms\Components\TextInput::make('total_amount')
                    ->label('Total')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\DateTimePicker::make('issued_at')
                    ->label('Fecha de emisión')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'paid' => 'Pagada',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'issued' => 'warning',
                        'paid' => 'success',
                        default => 'gray',
                    })
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('paymentMethod.name')
                    ->label('Forma de pago')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emitida')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'paid' => 'Pagada',
                    ]),
                Tables\Filters\Filter::make('issued_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Emitida desde'),
                        Forms\Components\DatePicker::make('until')->label('Emitida hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q) => $q->whereDate('issued_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q) => $q->whereDate('issued_at', '<=', $data['until']));
                    }),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Creado desde'),
                        Forms\Components\DatePicker::make('until')->label('Creado hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => $record->canRegisterPayment())
                    ->form(fn (Invoice $record): array => static::markAsPaidFormSchema($record))
                    ->action(fn (Invoice $record, array $data) => static::registerInvoicePayment($record, $data)),
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
        return [
            RelationManagers\InvoiceItemsRelationManager::class,
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInvoices::route('/'),
            'create' => Pages\CreateInvoice::route('/create'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
