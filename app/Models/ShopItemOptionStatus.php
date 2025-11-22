<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShopItemOptionStatus extends Model
{
    use HasFactory;

    protected $table = 'shop_item_option_status';

    protected $fillable = [
        'shop_id',
        'item_id',
        'item_option_group_id',
        'item_option_id',
        'status',
    ];

    /**
     * The shop this status belongs to.
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    /**
     * The item this status belongs to.
     */
    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * The option group this status belongs to.
     */
    public function group()
    {
        return $this->belongsTo(ItemOptionGroup::class, 'item_option_group_id');
    }

    /**
     * The option this status belongs to.
     */
    
    public function shopOptionStatuses()
    {
        return $this->hasMany(ShopItemOptionStatus::class, 'item_option_id');
    }
    

    public function optionGroup()
    {
        return $this->belongsTo(ItemOptionGroup::class, 'item_option_group_id');
    }
    public function option()
    {
        return $this->belongsTo(ItemOption::class, 'item_option_id');
    }
}
