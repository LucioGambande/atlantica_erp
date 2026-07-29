<?php

namespace App\Models\PaymentDetails;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class GenericPaymentDetail extends Model
{
    protected $fillable = [
        'notes',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'detail');
    }
}
