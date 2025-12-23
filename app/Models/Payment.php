<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{ protected $fillable = [
    'userid',
    'orderid',

    'stripe_payment_intent_id',
    'stripe_session_id',

    'status',
    'amount_cents',
    'currency',

    'raw_response',
];

protected $casts = [
    'raw_response' => 'array',
];
}
