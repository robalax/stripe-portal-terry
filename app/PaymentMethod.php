<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    //
    protected $table = 'payment_methods';

    protected $fillable = [
        'user_id',
        'stripe_customer_object',
        'stripe_id',
        'payment_method',
        'card_finger_print',
        'last4'
    ];
}
