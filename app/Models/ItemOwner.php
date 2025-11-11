<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemOwner extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'shop_id',
        'category_id',
        'inactive',
    ];

    // Relations
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class,'shop_id');
    }

    
}
