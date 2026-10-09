<?php

namespace App\Filament\Resources\PurchaseInvoiceResource\Pages;

use App\Filament\Resources\PurchaseInvoiceResource;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseInvoice extends CreateRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function afterCreate(): void
    {
        // Una compra recién creada todavía no tiene líneas: esto solo entra en
        // juego si se crea directamente como recibida y ya se le cargaron.
        app(StockService::class)->syncStockForPurchaseInvoice($this->getRecord());
    }
}
