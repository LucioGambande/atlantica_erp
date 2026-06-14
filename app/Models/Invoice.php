<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'credited_invoice_id',
        'invoice_number',
        'document_type',
        'status',
        'total_amount',
        'generates_stock_movement',
        'stock_movements_recorded',
        'issued_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'generates_stock_movement' => 'boolean',
            'stock_movements_recorded' => 'boolean',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creditedInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'credited_invoice_id');
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(self::class, 'credited_invoice_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isCreditNote(): bool
    {
        return $this->document_type === 'credit_note';
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function canBeCancelled(): bool
    {
        return $this->document_type === 'invoice'
            && ! $this->isCancelled()
            && $this->status !== 'draft';
    }

    public function canBeInvoicedFromOrder(): bool
    {
        return $this->document_type === 'invoice' && ! $this->isCancelled();
    }

    public function paidAmount(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function remainingAmount(): float
    {
        if ($this->isCreditNote() || $this->total_amount < 0) {
            return 0;
        }

        return max(0, round((float) $this->total_amount - $this->paidAmount(), 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid' || $this->remainingAmount() <= 0;
    }

    public function canRegisterPayment(): bool
    {
        return $this->document_type === 'invoice'
            && ! $this->isCancelled()
            && $this->status === 'issued'
            && $this->remainingAmount() > 0;
    }

    public function recalculateTotalFromItems(): void
    {
        $this->loadMissing('invoiceItems');

        $total = $this->invoiceItems->sum(
            fn (InvoiceItem $item): float => $item->discounted_total,
        );

        $this->update([
            'total_amount' => round($total, 2),
        ]);
    }
}
