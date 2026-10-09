<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn () => PurchaseInvoiceResource::releaseStock($this->getRecord())),
        ];
    }

    /**
     * Pasar la compra a "recibida" suma la mercadería al stock; volverla a
     * borrador la devuelve.
     */
    protected function afterSave(): void
    {
        app(StockService::class)->syncStockForPurchaseInvoice($this->getRecord());
    }
}
