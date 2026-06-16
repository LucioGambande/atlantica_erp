<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LedgerEntry extends Model
{
    use HasFactory;

    public const TYPE_INVOICE = 'invoice';

    public const TYPE_PAYMENT = 'payment';

    public const TYPE_CREDIT_NOTE = 'credit_note';

    public const TYPE_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'customer_id',
        'type',
        'reference_type',
        'reference_id',
        'date',
        'description',
        'debit',
        'credit',
        'running_balance',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'running_balance' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_INVOICE => 'Factura',
            self::TYPE_PAYMENT => 'Pago',
            self::TYPE_CREDIT_NOTE => 'Nota de crédito',
            self::TYPE_ADJUSTMENT => 'Ajuste',
            default => $this->type,
        };
    }
}
