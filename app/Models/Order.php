<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'userid', 'shopid', 'promoid',
        'status', 'subtotalcents', 'discountcents', 'totalcents',
        'placedat', 'updatedat'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'userid');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shopid');
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promoid');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'orderid');
    }
    public function orderItems()
{
    return $this->hasMany(\App\Models\OrderItem::class, 'orderid');
}
}
