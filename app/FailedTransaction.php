<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FailedTransaction extends Model
{
    //
    protected $table = 'failed_transactions';
    protected $fillable = [
        'user_id',
        'payment_method_id',
        'message'
    ];
}
