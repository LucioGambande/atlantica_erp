<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Support\TableUi;
use App\Filament\Resources\InvoiceResource\Pages;
use App\Filament\Resources\InvoiceResource\RelationManagers;
use App\Filament\Forms\PaymentDetailForm;
use App\Models\Invoice;
use App\Services\InvoiceNumberGenerator;
use App\Services\InvoicePrintService;
use App\Services\InvoiceSequenceValidator;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Support\InvoicePrintAuthorization;
use RuntimeException;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;
use InvalidArgumentException;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = NavigationGroups::FACTURACION;

    protected static ?int $navigationSort = 2;

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
            Forms\Components\Placeholder::make('remaining_preview')
                ->label('Saldo pendiente')
                ->content(Number::currency($invoice->remainingAmount(), 'EUR')),
            Forms\Components\TextInput::make('amount')
                ->label('Importe del cobro')
                ->required()
                ->numeric()
                ->default(fn (): float => $invoice->remainingAmount())
                ->minValue(0.01)
                ->maxValue(fn (): float => $invoice->remainingAmount())
                ->step(0.01)
                ->helperText('Podés registrar un pago parcial o el total pendiente.'),
            PaymentDetailForm::methodSelect(),
            PaymentDetailForm::detailsSection(),
            Forms\Components\DateTimePicker::make('paid_at')
                ->label('Fecha de pago')
                ->required()
                ->default(now()),
        ];
    }

    public static function cancelInvoice(Invoice $invoice): void
    {
        try {
            $creditNote = app(InvoiceService::class)->cancelInvoice($invoice);

            Notification::make()
                ->title('Factura cancelada')
                ->body("Nota de crédito {$creditNote->invoice_number} creada.")
                ->success()
                ->send();
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('No se pudo cancelar la factura')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function registerInvoicePayment(Invoice $invoice, array $data): void
    {
        try {
            $payment = app(PaymentService::class)->registerInvoicePayment(
                $invoice,
                (int) $data['payment_method_id'],
                is_array($data['detail'] ?? null) ? $data['detail'] : [],
                isset($data['paid_at']) ? Carbon::parse($data['paid_at']) : null,
                isset($data['amount']) ? (float) $data['amount'] : null,
            );

            $invoice->refresh();

            $title = $invoice->remainingAmount() > 0 ? 'Pago parcial registrado' : 'Pago registrado';
            $body = $invoice->remainingAmount() > 0
                ? 'Quedan '.Number::currency($invoice->remainingAmount(), 'EUR').' pendientes en la factura.'
                : 'La factura quedó liquidada.';

            Notification::make()
                ->title($title)
                ->body($body)
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

    public static function canViewAny(): bool
    {
        return InvoicePrintAuthorization::canPrint();
    }

    public static function canCreate(): bool
    {
        return InvoicePrintAuthorization::canManage();
    }

    public static function canEdit($record): bool
    {
        return InvoicePrintAuthorization::canManage();
    }

    public static function canView($record): bool
    {
        return InvoicePrintAuthorization::canPrint();
    }

    public static function canPrintInvoice(Invoice $invoice): bool
    {
        return InvoicePrintAuthorization::canPrint()
            && in_array($invoice->status, app(InvoicePrintService::class)->printableStatuses(), true);
    }

    public static function printAction(string $name = 'print'): Tables\Actions\Action
    {
        return Tables\Actions\Action::make($name)
            ->label('Imprimir')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->visible(fn (Invoice $record): bool => static::canPrintInvoice($record))
            ->url(fn (Invoice $record): string => route('invoices.print', $record))
            ->openUrlInNewTab();
    }

    public static function canDelete($record): bool
    {
        return false;
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
                    ->disabled(fn (?Invoice $record): bool => $record !== null),
                Forms\Components\Placeholder::make('linked_order')
                    ->label('Pedido vinculado')
                    ->content(function (?Invoice $record): HtmlString|string {
                        if ($record?->order_id === null) {
                            return 'Sin pedido. Las facturas desde pedido se crean con «Facturar pedido» en el pedido.';
                        }

                        $order = $record->order;
                        $url = OrderResource::getUrl('edit', ['record' => $order]);

                        return new HtmlString(
                            '<a href="'.e($url).'" class="text-primary-600 hover:underline font-medium">'
                            .'Pedido #'.$order->id
                            .'</a>'
                        );
                    })
                    ->visible(fn (?Invoice $record): bool => $record !== null),
                Forms\Components\TextInput::make('invoice_number')
                    ->label('Número de factura')
                    ->required()
                    ->maxLength(255)
                    ->unique(column: 'invoice_number', ignoreRecord: true)
                    ->default(fn (): string => app(InvoiceNumberGenerator::class)->preview())
                    ->helperText('Se sugiere el siguiente número correlativo del año. Podés cambiarlo antes de guardar.'),
                Forms\Components\DateTimePicker::make('issued_at')
                    ->label('Fecha de emisión')
                    ->default(now())
                    ->required(fn (Get $get): bool => in_array($get('status'), ['issued', 'paid'], true))
                    ->minDate(function (Get $get, ?Invoice $record): ?Carbon {
                        $invoiceNumber = $get('invoice_number');

                        if (blank($invoiceNumber)) {
                            return app(InvoiceSequenceValidator::class)->minimumIssuedAtForNextInYear();
                        }

                        return app(InvoiceSequenceValidator::class)->minimumIssuedAt(
                            $invoiceNumber,
                            $record?->id,
                        ) ?? app(InvoiceSequenceValidator::class)->minimumIssuedAtForNextInYear();
                    })
                    ->helperText('Debe ser igual o posterior a la factura con número anterior, y anterior o igual a la siguiente.'),
                Forms\Components\Select::make('document_type')
                    ->label('Tipo de documento')
                    ->options([
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota de crédito',
                    ])
                    ->default('invoice')
                    ->disabled()
                    ->dehydrated(),
                Forms\Components\Select::make('credited_invoice_id')
                    ->label('Factura rectificada')
                    ->relationship('creditedInvoice', 'invoice_number')
                    ->disabled()
                    ->visible(fn (?Invoice $record): bool => $record?->isCreditNote() ?? false),
                Forms\Components\Placeholder::make('cancelled_notice')
                    ->label('Estado de cancelación')
                    ->content(fn (?Invoice $record): string => $record?->cancelled_at?->format('d/m/Y H:i') ?? '')
                    ->visible(fn (?Invoice $record): bool => $record?->isCancelled() ?? false),
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
                    ->step(0.01)
                    ->disabled(),
                Forms\Components\Checkbox::make('generates_stock_movement')
                    ->label('Genera movimiento de stock')
                    ->default(true)
                    ->disabled(fn (?Invoice $record): bool => $record !== null && (
                        $record->stock_movements_recorded
                        || $record->isCreditNote()
                        || $record->isCancelled()
                    )),
                Forms\Components\Placeholder::make('stock_movements_recorded_notice')
                    ->label('Stock')
                    ->content('Movimientos de stock registrados')
                    ->visible(fn (?Invoice $record): bool => $record?->stock_movements_recorded ?? false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('invoice_number')
                    ->label('Número')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('document_type')
                    ->label('Tipo')
                    ->badge()
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('document_type', [
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota crédito',
                    ]))
                    ->toggleable()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota crédito',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'credit_note' => 'danger',
                        default => 'primary',
                    }),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('order.id')
                    ->label('Pedido')
                    ->placeholder('—')
                    ->toggleable()
                    ->url(fn (Invoice $record): ?string => $record->order_id
                        ? OrderResource::getUrl('edit', ['record' => $record->order_id])
                        : null)
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('status', [
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'partial' => 'Parcial',
                        'paid' => 'Pagada',
                    ]))
                    ->formatStateUsing(fn (Invoice $record): string => $record->paymentStatusLabel())
                    ->color(fn (Invoice $record): string => match (true) {
                        $record->isCancelled() => 'danger',
                        $record->status === 'draft' => 'gray',
                        $record->isPartiallyPaid() => 'info',
                        $record->status === 'issued' => 'warning',
                        $record->status === 'paid' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\IconColumn::make('generates_stock_movement')
                    ->label('Stock')
                    ->boolean()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('paid_amount')
                    ->label('Cobrado')
                    ->state(fn (Invoice $record): float => $record->paidAmount())
                    ->money('EUR')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('remaining_amount')
                    ->label('Pendiente')
                    ->state(fn (Invoice $record): float => $record->remainingAmount())
                    ->money('EUR')
                    ->color(fn (Invoice $record): string => $record->remainingAmount() > 0 ? 'danger' : 'success')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('issued_at')
                    ->label('Emitida')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'draft' => 'Borrador',
                        'issued' => 'Emitida',
                        'paid' => 'Pagada',
                    ]),
                Tables\Filters\SelectFilter::make('document_type')
                    ->label('Tipo')
                    ->options([
                        'invoice' => 'Factura',
                        'credit_note' => 'Nota crédito',
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
                static::printAction(),
                Tables\Actions\ViewAction::make()
                    ->visible(fn (): bool => ! InvoicePrintAuthorization::canManage()),
                Tables\Actions\Action::make('markAsPaid')
                    ->label('Registrar pago')
                    ->icon('heroicon-o-banknotes')
                    ->color('success')
                    ->visible(fn (Invoice $record): bool => InvoicePrintAuthorization::canManage() && $record->canRegisterPayment())
                    ->form(fn (Invoice $record): array => static::markAsPaidFormSchema($record))
                    ->action(fn (Invoice $record, array $data) => static::registerInvoicePayment($record, $data)),
                Tables\Actions\Action::make('cancelInvoice')
                    ->label('Cancelar factura')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Cancelar factura')
                    ->modalDescription('Se creará una nota de crédito con importes negativos. La factura original quedará cancelada.')
                    ->visible(fn (Invoice $record): bool => InvoicePrintAuthorization::canManage() && $record->canBeCancelled())
                    ->action(fn (Invoice $record) => static::cancelInvoice($record)),
                Tables\Actions\EditAction::make()
                    ->visible(fn (): bool => InvoicePrintAuthorization::canManage()),
            ])
            ->bulkActions([]);
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
            'view' => Pages\ViewInvoice::route('/{record}'),
            'edit' => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
