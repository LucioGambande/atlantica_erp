<?php

namespace App\Models\PaymentDetails;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BankTransferPaymentDetail extends Model
{
    protected $fillable = [
        'transaction_number',
        'bank_reference',
    ];

    public function payment(): MorphOne
    {
        return $this->morphOne(\App\Models\Payment::class, 'detail');
    }
}
