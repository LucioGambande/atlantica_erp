<?php

namespace App\Models;

use App\Services\InvoiceSequenceValidator;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class Invoice extends Model
{
    use HasFactory;

    protected static bool $skipSequenceValidation = false;

    public static function skipSequenceValidation(bool $skip = true): void
    {
        static::$skipSequenceValidation = $skip;
    }

    protected $fillable = [
        'customer_id',
        'order_id',
        'credited_invoice_id',
        'invoice_number',
        'legacy_invoice_number',
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

    protected static function booted(): void
    {
        static::saving(function (Invoice $invoice): void {
            if (static::$skipSequenceValidation) {
                return;
            }

            if (blank($invoice->invoice_number)) {
                return;
            }

            if (! $invoice->isDirty(['invoice_number', 'issued_at', 'status'])) {
                return;
            }

            if (! in_array($invoice->status, ['issued', 'paid'], true) && $invoice->issued_at === null) {
                return;
            }

            try {
                app(InvoiceSequenceValidator::class)->validate(
                    $invoice->invoice_number,
                    $invoice->issued_at ?? now(),
                    $invoice->exists ? $invoice->id : null,
                );
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    'issued_at' => $exception->getMessage(),
                ]);
            }
        });
    }

    public function shouldAffectStock(): bool
    {
        return in_array($this->status, ['issued', 'paid'], true);
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

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function paidAmount(): float
    {
        return round((float) $this->paymentAllocations()->sum('amount'), 2);
    }

    public function isPartiallyPaid(): bool
    {
        return $this->paidAmount() > 0 && $this->remainingAmount() > 0;
    }

    public function paymentStatusLabel(): string
    {
        if ($this->isCancelled()) {
            return 'Cancelada';
        }

        if ($this->status === 'draft') {
            return 'Borrador';
        }

        if ($this->status === 'paid' || $this->remainingAmount() <= 0) {
            return 'Pagada';
        }

        if ($this->isPartiallyPaid()) {
            return 'Parcial';
        }

        return 'Emitida';
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
            && in_array($this->status, ['issued', 'paid'], true)
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
