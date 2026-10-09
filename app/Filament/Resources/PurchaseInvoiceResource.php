<?php

namespace App\Filament\Resources;

use App\Filament\Navigation\NavigationGroups;
use App\Filament\Resources\PurchaseInvoiceResource\Pages;
use App\Filament\Resources\PurchaseInvoiceResource\RelationManagers;
use App\Filament\Support\LotField;
use App\Filament\Support\StatusBadge;
use App\Filament\Support\TableUi;
use App\Models\PurchaseInvoice;
use App\Services\StockService;
use App\Support\ErpAuthorization;
use App\Support\VatTotals;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class PurchaseInvoiceResource extends Resource
{
    protected static ?string $model = PurchaseInvoice::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-down';

    protected static ?string $navigationGroup = NavigationGroups::COMPRAS;

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'factura de compra';

    protected static ?string $pluralModelLabel = 'facturas de compra';

    protected static ?string $recordTitleAttribute = 'document_number';

    public static function canViewAny(): bool
    {
        return ErpAuthorization::isAdmin();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['document_number'];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['supplier', 'purchaseInvoiceItems']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('supplier_id')
                    ->relationship('supplier', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('document_number')
                    ->required()
                    ->maxLength(255)
                    ->unique(column: 'document_number', ignoreRecord: true),
                Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'received' => 'Recibida',
                        'paid' => 'Pagada',
                    ])
                    ->required()
                    ->default('draft'),
                Forms\Components\Toggle::make('generates_stock_movement')
                    ->label('Suma stock al recibirla')
                    ->helperText('Desactivalo para compras que no son mercadería (servicios, portes, gastos).')
                    ->default(true)
                    ->disabled(fn (?PurchaseInvoice $record): bool => $record?->stock_movements_recorded ?? false),
                Forms\Components\Placeholder::make('stock_movements_recorded_notice')
                    ->label('Stock')
                    ->content('Mercadería ingresada. Para corregir las líneas, volvé la compra a borrador.')
                    ->visible(fn (?PurchaseInvoice $record): bool => $record?->stock_movements_recorded ?? false),
                Forms\Components\TextInput::make('total_amount')
                    ->label('Total (con IVA)')
                    ->helperText('Importe final con IVA. Si hay líneas, el total se calcula desde ellas.')
                    ->required()
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                Forms\Components\DateTimePicker::make('received_at')
                    ->nullable(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('document_number')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('supplier.name')
                    ->label('Proveedor')
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => StatusBadge::purchaseInvoiceLabel($state))
                    ->color(fn (string $state): string => StatusBadge::purchaseInvoice($state))
                    ->extraHeaderAttributes(TableUi::headerSelectFilter('status', [
                        'draft' => 'Borrador',
                        'received' => 'Recibida',
                        'paid' => 'Pagada',
                    ]))
                    ->toggleable(),
                Tables\Columns\TextColumn::make('gross_amount')
                    ->label('Total')
                    ->state(fn (PurchaseInvoice $record): float => $record->grossAmount())
                    ->money('EUR')
                    ->sortable(query: function (Builder $query, string $direction): void {
                        $factor = VatTotals::factor();
                        $query->orderByRaw("(purchase_invoices.total_amount * {$factor}) {$direction}");
                    })
                    ->toggleable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('lot_id')
                    ->label('Lote')
                    ->options(fn (): array => LotField::allOptions())
                    ->searchable()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $lotId): Builder => $q->whereHas(
                            'purchaseInvoiceItems',
                            fn (Builder $items): Builder => $items->where('lot_id', $lotId),
                        ),
                    )),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Borrador',
                        'received' => 'Recibida',
                        'paid' => 'Pagada',
                    ]),
                Tables\Filters\Filter::make('received_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Recibida desde'),
                        Forms\Components\DatePicker::make('until')->label('Recibida hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q) => $q->whereDate('received_at', '>=', $data['from']))
                            ->when($data['until'] ?? null, fn (Builder $q) => $q->whereDate('received_at', '<=', $data['until']));
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(fn (PurchaseInvoice $record) => static::releaseStock($record)),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->before(fn (Collection $records) => $records->each(
                            fn (PurchaseInvoice $record) => static::releaseStock($record),
                        )),
                ]),
            ]);
    }

    /**
     * Borrar una compra ya recibida tiene que devolver el stock que sumó:
     * si no, la mercadería queda en el depósito sin respaldo documental.
     */
    public static function releaseStock(PurchaseInvoice $purchaseInvoice): void
    {
        app(StockService::class)->reverseStockFromPurchaseInvoice($purchaseInvoice);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PurchaseInvoiceItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPurchaseInvoices::route('/'),
            'create' => Pages\CreatePurchaseInvoice::route('/create'),
            'edit' => Pages\EditPurchaseInvoice::route('/{record}/edit'),
        ];
    }
}
