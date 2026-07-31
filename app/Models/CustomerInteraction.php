<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'user_id',
        'type',
        'happened_at',
        'notes',
        'next_action_type',
        'next_action_at',
        'next_action_notes',
        'source',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'next_action_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
