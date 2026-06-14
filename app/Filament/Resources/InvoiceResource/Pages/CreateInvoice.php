<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\StockService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
