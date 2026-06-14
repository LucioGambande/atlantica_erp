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
        'invoice_number',
        'status',
        'total_amount',
        'issued_at',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'issued_at' => 'datetime',
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

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): float
    {
        return round((float) $this->payments()->sum('amount'), 2);
    }

    public function remainingAmount(): float
    {
        return max(0, round((float) $this->total_amount - $this->paidAmount(), 2));
    }

    public function isFullyPaid(): bool
    {
        return $this->status === 'paid' || $this->remainingAmount() <= 0;
    }

    public function canRegisterPayment(): bool
    {
        return $this->status === 'issued' && $this->remainingAmount() > 0;
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
