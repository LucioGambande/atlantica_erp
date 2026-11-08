<?php

namespace App\Filament\Resources\StockTransferResource\Pages;

use App\Filament\Resources\StockTransferResource;
use App\Services\StockService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStockTransfer extends EditRecord
{
    protected static string $resource = StockTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(fn () => StockTransferResource::releaseStock($this->getRecord())),
        ];
    }

    /**
     * Pasar el traslado a "completado" mueve el stock; volverlo a
     * borrador lo revierte.
     */
    protected function afterSave(): void
    {
        app(StockService::class)->syncStockForTransfer($this->getRecord());
    }
}
