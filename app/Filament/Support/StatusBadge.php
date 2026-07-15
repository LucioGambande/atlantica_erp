<?php

namespace App\Filament\Support;

use App\Models\Invoice;
use App\Models\LedgerEntry;

/**
 * Colores semáforo para badges de estado en Filament.
 *
 * Verde  = resuelto / OK (pagada, completado, liquidada)
 * Amarillo = en curso / parcial / emitida
 * Rojo   = pendiente crítico / cancelada / sin stock
 * Gris   = neutro (borrador, ajustes)
 */
class StatusBadge
{
    public const SUCCESS = 'success';

    public const WARNING = 'warning';

    public const DANGER = 'danger';

    public const NEUTRAL = 'gray';

    public static function invoice(Invoice $invoice): string
    {
        if ($invoice->isCancelled()) {
            return self::DANGER;
        }

        if ($invoice->status === 'draft') {
            return self::NEUTRAL;
        }

        if ($invoice->status === 'paid' || $invoice->remainingAmount() <= 0) {
            return self::SUCCESS;
        }

        if ($invoice->isPartiallyPaid()) {
            return self::WARNING;
        }

        if ($invoice->status === 'issued') {
            return self::WARNING;
        }

        return self::NEUTRAL;
    }

    public static function order(string $status): string
    {
        return match ($status) {
            'completed' => self::SUCCESS,
            'pending' => self::WARNING,
            'cancelled' => self::DANGER,
            default => self::NEUTRAL,
        };
    }

    public static function orderLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Pendiente',
            'completed' => 'Completado',
            'cancelled' => 'Cancelado',
            default => $status,
        };
    }

    public static function purchaseInvoice(string $status): string
    {
        return match ($status) {
            'paid' => self::SUCCESS,
            'received' => self::WARNING,
            'draft' => self::NEUTRAL,
            default => self::NEUTRAL,
        };
    }

    public static function purchaseInvoiceLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Borrador',
            'received' => 'Recibida',
            'paid' => 'Pagada',
            default => $status,
        };
    }

    public static function settlement(?string $status): string
    {
        return match ($status) {
            'Liquidada' => self::SUCCESS,
            'Parcial' => self::WARNING,
            'Pendiente' => self::DANGER,
            default => self::NEUTRAL,
        };
    }

    public static function ledgerEntryType(string $type): string
    {
        return match ($type) {
            LedgerEntry::TYPE_PAYMENT, LedgerEntry::TYPE_CREDIT_NOTE => self::SUCCESS,
            LedgerEntry::TYPE_INVOICE => self::WARNING,
            LedgerEntry::TYPE_ADJUSTMENT => self::NEUTRAL,
            default => self::NEUTRAL,
        };
    }

    public static function stockLevel(int $stock, int $lowThreshold = 5): string
    {
        if ($stock <= 0) {
            return self::DANGER;
        }

        if ($stock <= $lowThreshold) {
            return self::WARNING;
        }

        return self::SUCCESS;
    }
}
