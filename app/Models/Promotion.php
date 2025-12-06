<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'shopid', 'code', 'type', 'value',
        'startsat', 'endsat', 'isactive', 'usagelimit'
    ];

    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shopid');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'promoid');
    }
}
