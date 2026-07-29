<?php

namespace App\Models\PaymentDetails;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class ChequePaymentDetail extends Model
{
    protected $fillable = [
        'cheque_number',
        'bank_name',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'detail');
    }
}
