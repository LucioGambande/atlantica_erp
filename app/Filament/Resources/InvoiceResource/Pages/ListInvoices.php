<?php

namespace App\Filament\Resources\InvoiceResource\Pages;

use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Support\InvoicePrintAuthorization;
use Closure;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return function (Invoice $record): string {
            if (InvoicePrintAuthorization::canManage()) {
                return InvoiceResource::getUrl('edit', ['record' => $record]);
            }

            return InvoiceResource::getUrl('view', ['record' => $record]);
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->visible(fn (): bool => InvoicePrintAuthorization::canManage()),
        ];
    }
}
