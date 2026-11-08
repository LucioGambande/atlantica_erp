<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Services\StockService;
use Filament\Resources\Pages\CreateRecord;

class CreateStockTransfer extends CreateRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function afterCreate(): void
    {
        // Un traslado recién creado todavía no tiene líneas: esto solo
        // entra en juego si se crea directamente como completado y ya se
        // le cargaron.
        app(StockService::class)->syncStockForTransfer($this->getRecord());
    }
}
