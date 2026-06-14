<?php

namespace App\Models\PaymentDetails;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CashPaymentDetail extends Model
{
    protected $fillable = [
        'notes',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(\App\Models\Payment::class, 'detail');
    }
}
