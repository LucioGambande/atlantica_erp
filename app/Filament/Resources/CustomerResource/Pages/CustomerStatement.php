<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Forms\PaymentAllocationForm;
use App\Filament\Forms\PaymentDetailForm;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Services\AccountStatementService;
use App\Services\PaymentService;
use App\Support\ErpAuthorization;
use Filament\Actions;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class CustomerStatement extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static string $resource = CustomerResource::class;

    protected static string $view = 'filament.resources.customer-resource.pages.customer-statement';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public int $customerId;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $entryType = 'all';

    public function mount(int|string|Customer $record): void
    {
        $this->customerId = $this->resolveCustomerId($record);
    }

    public function getCustomer(): Customer
    {
        return CustomerResource::getEloquentQuery()
            ->findOrFail($this->customerId);
    }

    protected function getForms(): array
    {
        return ['filtersForm'];
    }

    public function getTitle(): string
    {
        return 'Cuenta corriente — '.$this->getCustomer()->name;
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\Action::make('registerPayment')
                ->label('Registrar cobro')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => ErpAuthorization::userCan('manage invoices'))
                ->form([
                    Forms\Components\TextInput::make('amount')
                        ->label('Importe del cobro')
                        ->required()
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->live(onBlur: true),
                    PaymentAllocationForm::allocatedSummaryPlaceholder(),
                    PaymentAllocationForm::allocationsRepeater($this->customerId),
                    PaymentDetailForm::methodSelect(),
                    PaymentDetailForm::detailsSection(),
                    Forms\Components\DateTimePicker::make('paid_at')
                        ->label('Fecha de pago')
                        ->required()
                        ->default(now()),
                ])
                ->fillForm(fn (): array => [
                    'allocations' => [],
                ])
                ->mutateFormDataUsing(function (array $data): array {
                    $data['customer_id'] = $this->customerId;

                    return $data;
                })
                ->action(function (array $data): void {
                    try {
                        app(PaymentService::class)->registerPayment([
                            'customer_id' => $this->customerId,
                            'amount' => $data['amount'],
                            'payment_method_id' => $data['payment_method_id'],
                            'detail' => is_array($data['detail'] ?? null) ? $data['detail'] : [],
                            'paid_at' => $data['paid_at'] ?? now(),
                            'allocations' => is_array($data['allocations'] ?? null) ? $data['allocations'] : [],
                        ]);

                        $this->resetTable();

                        Notification::make()
                            ->title('Cobro registrado')
                            ->success()
                            ->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()
                            ->title('No se pudo registrar el cobro')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            Actions\Action::make('print')
                ->label('Imprimir')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => route('customers.statement.print', [
                    'customer' => $this->customerId,
                    'from' => $this->dateFrom,
                    'to' => $this->dateTo,
                    'type' => $this->entryType,
                    'format' => 'html',
                ]))
                ->openUrlInNewTab(),
            Actions\Action::make('editCustomer')
                ->label('Editar cliente')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => CustomerResource::getUrl('edit', ['record' => $this->customerId])),
        ];

        if ($this->shouldOfferLedgerRebuild()) {
            $actions[] = Actions\Action::make('rebuildLedger')
                ->label('Importar movimientos')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Importar movimientos históricos')
                ->modalDescription('Genera el libro mayor desde las facturas y pagos existentes de este cliente. Solo hace falta la primera vez o tras correcciones manuales en la base de datos.')
                ->action(function (): void {
                    app(AccountStatementService::class)->rebuildLedger($this->getCustomer());
                    $this->resetTable();
                    Notification::make()
                        ->title('Movimientos importados')
                        ->success()
                        ->send();
                });
        }

        return $actions;
    }

    public function shouldOfferLedgerRebuild(): bool
    {
        $customer = $this->getCustomer();

        if ($customer->ledgerEntries()->exists()) {
            return false;
        }

        return $customer->invoices()->whereIn('status', ['issued', 'paid'])->exists()
            || $customer->payments()->exists();
    }

    public function getStatementSummary(): array
    {
        return app(AccountStatementService::class)->getStatement(
            $this->getCustomer(),
            $this->dateFrom ? Carbon::parse($this->dateFrom) : null,
            $this->dateTo ? Carbon::parse($this->dateTo) : null,
            $this->entryType === 'all' ? null : $this->entryType,
        );
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(function () {
                $query = LedgerEntry::query()
                    ->where('customer_id', $this->customerId)
                    ->with('reference');

                if ($this->dateFrom) {
                    $query->whereDate('date', '>=', $this->dateFrom);
                }

                if ($this->dateTo) {
                    $query->whereDate('date', '<=', $this->dateTo);
                }

                if ($this->entryType === 'invoice') {
                    $query->whereIn('type', [
                        LedgerEntry::TYPE_INVOICE,
                        LedgerEntry::TYPE_CREDIT_NOTE,
                    ]);
                } elseif ($this->entryType === 'payment') {
                    $query->where('type', LedgerEntry::TYPE_PAYMENT);
                }

                return $query->orderBy('date')->orderBy('id');
            })
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (LedgerEntry $record): string => $record->typeLabel())
                    ->color(fn (LedgerEntry $record): string => match ($record->type) {
                        LedgerEntry::TYPE_INVOICE => 'warning',
                        LedgerEntry::TYPE_PAYMENT => 'success',
                        LedgerEntry::TYPE_CREDIT_NOTE => 'info',
                        LedgerEntry::TYPE_ADJUSTMENT => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('description')
                    ->label('Descripción')
                    ->searchable()
                    ->wrap(),
                Tables\Columns\TextColumn::make('debit')
                    ->label('Débito')
                    ->money('EUR')
                    ->color('danger')
                    ->placeholder('—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('credit')
                    ->label('Crédito')
                    ->money('EUR')
                    ->color('success')
                    ->placeholder('—')
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('running_balance')
                    ->label('Saldo')
                    ->money('EUR')
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewReference')
                    ->label(fn (LedgerEntry $record): string => $record->reference instanceof Payment ? 'Editar' : 'Ver')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(function (LedgerEntry $record): ?string {
                        if ($record->reference instanceof Invoice) {
                            return InvoiceResource::getUrl('edit', ['record' => $record->reference]);
                        }

                        if ($record->reference instanceof Payment) {
                            return PaymentResource::getUrl('edit', ['record' => $record->reference]);
                        }

                        return null;
                    })
                    ->visible(fn (LedgerEntry $record): bool => $record->reference instanceof Invoice
                        || $record->reference instanceof Payment),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin movimientos en el libro mayor')
            ->emptyStateDescription(fn (): string => $this->shouldOfferLedgerRebuild()
                ? 'Este cliente tiene facturas o pagos, pero aún no se importaron al libro mayor. Usá el botón «Importar movimientos» arriba a la derecha.'
                : 'No hay entradas en el período seleccionado.');
    }

    public function filtersForm(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('dateFrom')
                    ->label('Desde')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
                Forms\Components\DatePicker::make('dateTo')
                    ->label('Hasta')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
                Forms\Components\Select::make('entryType')
                    ->label('Tipo')
                    ->options([
                        'all' => 'Todos',
                        'invoice' => 'Solo facturas',
                        'payment' => 'Solo pagos',
                    ])
                    ->default('all')
                    ->live()
                    ->afterStateUpdated(fn () => $this->resetTable()),
            ])
            ->columns(3);
    }

    protected function resolveCustomerId(int|string|Customer|array $record): int
    {
        if ($record instanceof Customer) {
            return (int) $record->getKey();
        }

        if (is_array($record)) {
            return (int) ($record['id'] ?? $record['key'] ?? 0);
        }

        return (int) $record;
    }
}
