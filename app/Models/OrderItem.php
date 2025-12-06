<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'orderid',
        'itemid',
        'namesnapshot',
        'unitprice_cents',
        'quantity',
        'notes',
        'option_groups' // FIXED (no space)
    ];

    protected $casts = [
        'option_groups' => 'array', // JSON → array
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'itemid');
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'orderid');
    }
}
