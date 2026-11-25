<?php

namespace App\Filament\Support;

use App\Models\Invoice;
use Filament\Tables\Actions\Action;

class InvoiceItemsModalAction
{
    /**
     * Acción de tabla que abre un modal con el detalle (líneas) de una
     * factura. $resolveInvoice recibe el record de la fila (que puede
     * ser directamente un Invoice, o un LedgerEntry/otro modelo que
     * referencia uno) y devuelve la factura a mostrar, o null si la
     * fila no corresponde a una factura.
     *
     * @param  callable(mixed $record): ?Invoice  $resolveInvoice
     */
    public static function make(callable $resolveInvoice): Action
    {
        return Action::make('viewInvoiceItems')
            ->label('Ver detalle')
            ->icon('heroicon-o-list-bullet')
            ->color('gray')
            ->modalHeading(function (mixed $record) use ($resolveInvoice): string {
                $invoice = $resolveInvoice($record);

                return $invoice instanceof Invoice
                    ? 'Detalle — '.$invoice->invoice_number
                    : 'Detalle de factura';
            })
            ->modalContent(function (mixed $record) use ($resolveInvoice) {
                $invoice = $resolveInvoice($record);

                if (! $invoice instanceof Invoice) {
                    return null;
                }

                $invoice->loadMissing('invoiceItems.product');

                return view('filament.modals.invoice-items', ['invoice' => $invoice]);
            })
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->visible(fn (mixed $record): bool => $resolveInvoice($record) instanceof Invoice);
    }
}
