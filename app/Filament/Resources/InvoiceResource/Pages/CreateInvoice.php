<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\AccountStatementService;
use App\Services\InvoiceNumberGenerator;
use App\Services\StockService;
use App\Support\LineItemTotals;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    /** @var list<array<string, mixed>> */
    protected array $lineItems = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['invoice_number'] ?? null)) {
            $data['invoice_number'] = app(InvoiceNumberGenerator::class)->next();
        }

        if (blank($data['issued_at'] ?? null)) {
            $data['issued_at'] = now();
        }

        $this->lineItems = array_values(array_filter(
            is_array($data['line_items'] ?? null) ? $data['line_items'] : [],
            fn ($row): bool => is_array($row) && filled($row['product_id'] ?? null),
        ));

        unset($data['line_items']);

        $data['total_amount'] = round(
            collect($this->lineItems)->sum(fn (array $row): float => LineItemTotals::discountedLineTotal(
                (float) ($row['unit_price'] ?? 0),
                (int) ($row['quantity'] ?? 0),
                (float) ($row['discount_percent'] ?? 0),
            )),
            2,
        );

        return $data;
    }

    protected function afterCreate(): void
    {
        $invoice = $this->getRecord();

        foreach ($this->lineItems as $row) {
            $invoice->invoiceItems()->create([
                'product_id' => (int) $row['product_id'],
                'description' => $row['description'] ?? 'Línea de factura',
                'quantity' => (int) ($row['quantity'] ?? 0),
                'unit_price' => (float) ($row['unit_price'] ?? 0),
                'discount_percent' => (float) ($row['discount_percent'] ?? 0),
                'total_price' => LineItemTotals::discountedLineTotal(
                    (float) ($row['unit_price'] ?? 0),
                    (int) ($row['quantity'] ?? 0),
                    (float) ($row['discount_percent'] ?? 0),
                ),
            ]);
        }

        $invoice->recalculateTotalFromItems();

        app(AccountStatementService::class)->registerInvoice($invoice->fresh(['invoiceItems', 'customer']));

        $invoice = $invoice->fresh();

        if (! $invoice->generates_stock_movement || $invoice->status !== 'issued') {
            return;
        }

        try {
            app(StockService::class)->applyStockFromInvoice($invoice);
        } catch (DomainException $exception) {
            Notification::make()
                ->title('Stock no registrado')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }
}
