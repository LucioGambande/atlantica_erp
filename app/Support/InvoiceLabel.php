<?php

namespace App\Support;

use App\Models\Invoice;
use Illuminate\Support\Number;

class InvoiceLabel
{
    public static function numberAndDate(Invoice $invoice): string
    {
        $label = $invoice->invoice_number;

        if ($invoice->issued_at !== null) {
            $label .= ' · '.$invoice->issued_at->format('d/m/Y');
        }

        return $label;
    }

    public static function withPendingAmount(Invoice $invoice, ?float $pendingAmount = null): string
    {
        $pending = $pendingAmount ?? $invoice->remainingAmount();

        return static::numberAndDate($invoice).' — pendiente '.Number::currency($pending, 'EUR');
    }

    public static function withAllocatedAmount(Invoice $invoice, float $amount): string
    {
        return static::numberAndDate($invoice).': '.number_format($amount, 2, ',', '.').' €';
    }
}
