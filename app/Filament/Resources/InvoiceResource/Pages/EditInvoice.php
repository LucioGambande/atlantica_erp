<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use Filament\Actions;
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
            Actions\DeleteAction::make(),
        ];
    }
}
