<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\InvoiceNumberGenerator;
use App\Services\StockService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['invoice_number'] ?? null)) {
            $data['invoice_number'] = app(InvoiceNumberGenerator::class)->next();
        }

        if (blank($data['issued_at'] ?? null)) {
            $data['issued_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $invoice = $this->getRecord()->fresh();

        if (
            ! $invoice->generates_stock_movement
            || $invoice->status !== 'issued'
        ) {
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
