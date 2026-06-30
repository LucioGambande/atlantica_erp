<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Services\PriceResolutionService;
use App\Services\InvoiceService;
use App\Support\ErpAuthorization;
use App\Support\LineItemTotals;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use RuntimeException;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?string $navigationGroup = 'ERP';

    protected static ?string $modelLabel = 'pedido';

    protected static ?string $pluralModelLabel = 'pedidos';

    protected static ?string $recordTitleAttribute = 'id';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::userCan('manage orders');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['id'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        /** @var Order $record */
        return [
            'Cliente' => $record->customer?->name ?? '—',
            'Estado' => $record->status,
        ];
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    public static function invoiceOrderFormSchema(): array
    {
        return [
            Forms\Components\Checkbox::make('generates_stock_movement')
                ->label('Genera movimiento de stock')
                ->default(true),
        ];
    }

    public static function invoiceOrder(Order $order, array $data): ?Invoice
    {
        try {
            $invoice = app(InvoiceService::class)->createFromOrder(
                $order,
                (bool) ($data['generates_stock_movement'] ?? true),
            );

            Notification::make()
                ->title('Factura creada')
                ->body("Factura {$invoice->invoice_number} generada correctamente.")
                ->success()
                ->send();

            return $invoice;
        } catch (RuntimeException $exception) {
            Notification::make()
                ->title('No se pudo facturar el pedido')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return null;
        }
    }

    public static function recalculateLineTotal(Set $set, Get $get): void
    {
        $qty = max(0, (int) $get('quantity'));
        $unit = max(0, (float) $get('unit_price'));
        $disc = (float) ($get('discount_percent') ?? 0);
        $set('total_price', LineItemTotals::discountedLineTotal($unit, $qty, $disc));
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos del pedido')
                    ->schema([
                        Forms\Components\Select::make('customer_id')
                            ->label('Cliente')
                            ->relationship('customer', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->label('Estado')
                            ->options([
                                'pending' => 'Pendiente',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                            ])
                            ->required()
                            ->default('pending'),
                        Forms\Components\DateTimePicker::make('ordered_at')
                            ->label('Fecha del pedido')
                            ->default(now())
                            ->required(fn (?Order $record): bool => $record === null),
                        Forms\Components\Hidden::make('total_amount')
                            ->default(0),
                        Forms\Components\Placeholder::make('order_total_preview')
                            ->label('Total del pedido (vista previa)')
                            ->content(function (Get $get): string {
                                $lines = $get('orderItems') ?? [];
                                if (! is_array($lines)) {
                                    return '—';
                                }
                                $sum = collect($lines)->sum(function ($row) {
                                    if (! is_array($row)) {
                                        return 0;
                                    }

                                    return (float) ($row['total_price'] ?? 0);
                                });

                                return Number::currency($sum, 'EUR');
                            }),
                    ])
                    ->columns(2),
                Forms\Components\Section::make('Líneas del pedido')
                    ->description('PVP al elegir producto (editable). Eliminar con el icono a la derecha.')
                    ->schema([
                        Forms\Components\Repeater::make('orderItems')
                            ->relationship()
                            ->live()
                            ->columns(12)
                            ->minItems(1)
                            ->defaultItems(1)
                            ->itemLabel(null)
                            ->collapsible(false)
                            ->reorderable(false)
                            ->reorderableWithDragAndDrop(false)
                            ->extraAttributes(['class' => 'order-items-one-line'])
                            ->deleteAction(
                                fn ($action) => $action->tooltip(__('Eliminar línea'))
                            )
                            ->schema([
                                Forms\Components\Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->orderBy('name'),
                                    )
                                    ->getOptionLabelFromRecordUsing(
                                        fn (Product $record): string => $record->name.' · '.$record->sku
                                    )
                                    ->searchable(['name', 'sku'])
                                    ->preload()
                                    ->required()
                                    ->columnSpan(5)
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set, Get $get): void {
                                        if (! $state) {
                                            return;
                                        }
                                        $product = Product::query()->find($state);
                                        if ($product) {
                                            $customerId = $get('../../customer_id');
                                            $unitPrice = app(PriceResolutionService::class)
                                                ->resolvePriceForCustomerId($product, $customerId ? (int) $customerId : null);
                                            $set('unit_price', $unitPrice);
                                        }
                                        static::recalculateLineTotal($set, $get);
                                    }),
                                Forms\Components\TextInput::make('quantity')
                                    ->label('Cant.')
                                    ->required()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->columnSpan(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        static::recalculateLineTotal($set, $get);
                                    }),
                                Forms\Components\TextInput::make('unit_price')
                                    ->label('P. unit.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix('€')
                                    ->columnSpan(2)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        static::recalculateLineTotal($set, $get);
                                    }),
                                Forms\Components\TextInput::make('discount_percent')
                                    ->label('Dto. (%)')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->columnSpan(1)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, Get $get): void {
                                        static::recalculateLineTotal($set, $get);
                                    }),
                                Forms\Components\TextInput::make('total_price')
                                    ->label('Subtotal')
                                    ->numeric()
                                    ->readOnly()
                                    ->prefix('€')
                                    ->columnSpan(2)
                                    ->dehydrated()
                                    ->default(0),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_amount')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice.invoice_number')
                    ->label('Factura')
                    ->placeholder('—')
                    ->url(fn (Order $record): ?string => $record->invoice
                        ? InvoiceResource::getUrl('edit', ['record' => $record->invoice])
                        : null),
                Tables\Columns\TextColumn::make('ordered_at')
                    ->label('Fecha pedido')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pendiente',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde'),
                        Forms\Components\DatePicker::make('until')->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('invoiceOrder')
                    ->label('Facturar pedido')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->visible(fn (Order $record): bool => $record->canBeInvoiced())
                    ->form(static::invoiceOrderFormSchema())
                    ->action(function (Order $record, array $data, $livewire): void {
                        $invoice = static::invoiceOrder($record, $data);

                        if ($invoice !== null) {
                            $livewire->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                        }
                    }),
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
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
