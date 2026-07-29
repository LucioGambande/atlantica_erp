<?php

namespace App\Models\PaymentDetails;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BizumPaymentDetail extends Model
{
    protected $fillable = [
        'operation_code',
        'phone',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(Payment::class, 'detail');
    }
}
