<?php

namespace App\Filament\Resources\CustomerResource\Widgets;

use App\Models\Customer;
use App\Models\InvoiceItem;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CustomerProductsWidget extends BaseWidget
{
    protected static ?string $heading = 'Productos comprados';

    protected int|string|array $columnSpan = 'full';

    public ?Model $record = null;

    public function getCustomer(): ?Customer
    {
        return $this->record instanceof Customer ? $this->record : null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->getCustomer()
                ? $this->productsQuery($this->getCustomer())
                : InvoiceItem::query()->whereRaw('1 = 0'))
            ->columns([
                Tables\Columns\TextColumn::make('product_sku')
                    ->label('SKU')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('product_name')
                    ->label('Producto')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('net_quantity')
                    ->label('Cantidad (neta)')
                    ->numeric()
                    ->sortable()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('net_amount')
                    ->label('Importe (sin IVA, neto)')
                    ->money('EUR')
                    ->sortable()
                    ->alignEnd(),
            ])
            ->defaultSort('net_amount', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->bulkActions([])
            ->emptyStateHeading('Sin compras registradas');
    }

    /**
     * Cantidad e importe netos por producto: las notas de crédito restan
     * (la cantidad se invierte porque se guarda siempre positiva; el
     * importe ya viene negativo desde InvoiceService, ver InvoiceItem).
     */
    protected function productsQuery(Customer $customer): Builder
    {
        return InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->where('invoices.customer_id', $customer->id)
            ->whereIn('invoices.status', ['issued', 'paid'])
            ->groupBy('invoice_items.product_id', 'products.name', 'products.sku')
            ->selectRaw('invoice_items.product_id as id')
            ->selectRaw('invoice_items.product_id as product_id')
            ->selectRaw('products.name as product_name')
            ->selectRaw('products.sku as product_sku')
            ->selectRaw("SUM(CASE WHEN invoices.document_type = 'credit_note' THEN -invoice_items.quantity ELSE invoice_items.quantity END) as net_quantity")
            ->selectRaw('SUM(invoice_items.total_price) as net_amount');
    }
}
