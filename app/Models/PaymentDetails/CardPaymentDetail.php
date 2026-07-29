<?php

namespace App\Models\PaymentDetails;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class CardPaymentDetail extends Model
{
    protected $fillable = [
        'authorization_code',
        'card_last_four',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'detail');
    }
}
