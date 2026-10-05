<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\OrderResource;
use App\Services\StockService;
use DomainException;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (blank($data['ordered_at'] ?? null)) {
            $data['ordered_at'] = now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->recalculateTotalFromItems();

        // Los pedidos de clientes individuales descuentan stock al
        // crearse (igual que los que llegan por la API de la web), ya
        // que no esperan a facturarse para eso: la factura que generen
        // (interna, sin numeración fiscal) nunca vuelve a tocar el stock.
        if ($this->record->customer?->customer_type !== 'individual') {
            return;
        }

        try {
            app(StockService::class)->reduceStockFromOrder($this->record);
        } catch (DomainException $exception) {
            Notification::make()
                ->title('No se pudo descontar el stock del pedido')
                ->body($exception->getMessage())
                ->warning()
                ->send();
        }
    }
}
