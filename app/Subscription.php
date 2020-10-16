<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    //
    protected $fillable = [
        'user_id',
        'name',
        'stripe_id',
        'stripe_status',
        'stripe_plan',
        'plan_id',
        'payment_method',
        'status',
        'charge_object',
        'ends_at',
        'transaction_id',
        'charge_id',
        'amount_charged',
        'payment_method_id'
    ];
}
