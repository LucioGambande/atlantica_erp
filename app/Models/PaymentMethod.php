<?php

namespace App\Models;

use App\Support\PaymentDetailType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'detail_type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PaymentMethod $method): void {
            if (blank($method->slug) && filled($method->name)) {
                $method->slug = Str::slug($method->name);
            }
        });
    }

    /**
     * @return Builder<PaymentMethod>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return array<int, string>
     */
    public static function activeOptions(): array
    {
        return static::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function detailTypeLabel(): string
    {
        return PaymentDetailType::labels()[$this->detail_type] ?? $this->detail_type;
    }

    /**
     * @return class-string
     */
    public function detailModelClass(): string
    {
        return PaymentDetailType::modelClass($this->detail_type);
    }
}
