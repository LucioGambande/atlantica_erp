<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Services\StockService;
use DomainException;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('markAsPaid')
                ->label('Registrar pago')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->visible(fn (): bool => $this->getRecord()->canRegisterPayment())
                ->form(fn (): array => InvoiceResource::markAsPaidFormSchema($this->getRecord()))
                ->action(fn (array $data) => InvoiceResource::registerInvoicePayment($this->getRecord(), $data)),
            Actions\Action::make('cancelInvoice')
                ->label('Cancelar factura')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancelar factura')
                ->modalDescription('Se creará una nota de crédito con importes negativos. La factura original quedará cancelada.')
                ->visible(fn (): bool => $this->getRecord()->canBeCancelled())
                ->action(function (): void {
                    InvoiceResource::cancelInvoice($this->getRecord());
                    $creditNote = $this->getRecord()->fresh()->creditNotes()->latest()->first();
                    if ($creditNote) {
                        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $creditNote]));
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $invoice = $this->getRecord()->fresh();

        if (
            ! $invoice->generates_stock_movement
            || $invoice->stock_movements_recorded
            || $invoice->isCreditNote()
            || $invoice->isCancelled()
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
