<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\OrderResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('invoiceOrder')
                ->label('Facturar pedido')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->canBeInvoiced())
                ->form(OrderResource::invoiceOrderFormSchema())
                ->action(function (array $data): void {
                    $invoice = OrderResource::invoiceOrder($this->getRecord(), $data);

                    if ($invoice !== null) {
                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $invoice]));
                    }
                }),
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotalFromItems();
    }
}
