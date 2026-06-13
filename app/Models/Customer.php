<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'tax_id',
        'email',
        'phone',
        'website',
        'address',
        'city',
        'postal_code',
        'country',
        'customer_type',
        'credit_limit',
        'hubspot_company_id',
        'hubspot_last_modified_at',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'hubspot_last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function getBalanceAttribute(): string
    {
        $issuedInvoicesTotal = $this->invoices()
            ->where('status', 'issued')
            ->sum('total_amount');

        $paymentsTotal = $this->payments()
            ->sum('amount');

        return number_format($issuedInvoicesTotal - $paymentsTotal, 2, '.', '');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
