<?php

namespace App\Models;

use App\Services\AccountStatementService;
use App\Services\PriceResolutionService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'price_list_id',
        'name',
        'tax_id',
        'email',
        'balance',
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
        'hubspot_properties',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credit_limit' => 'decimal:2',
            'hubspot_last_modified_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'hubspot_properties' => 'array',
        ];
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function recalculateBalance(): void
    {
        app(AccountStatementService::class)->recalculateRunningBalances($this);
    }

    public function scopeWithDebt(Builder $query): Builder
    {
        return $query->where('balance', '>', 0);
    }

    public function scopeWithCredit(Builder $query): Builder
    {
        return $query->where('balance', '<', 0);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function getEffectivePriceList(): ?PriceList
    {
        if ($this->price_list_id !== null) {
            return $this->priceList;
        }

        return PriceList::query()
            ->active()
            ->where('is_default', true)
            ->first();
    }

    public function getPriceForProduct(Product $product): float
    {
        return app(PriceResolutionService::class)->resolvePrice($product, $this);
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
