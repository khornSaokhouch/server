<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemOption extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_option_group_id',
        'name',
        'price_adjust_cents',
        'icon',
        'is_active',
    ];

    /**
     * Relationship: Option belongs to a group
     */
    protected $appends = ['icon_url']; // Automatically include in JSON
    public function group()
    {
        return $this->belongsTo(ItemOptionGroup::class, 'item_option_group_id');
    }
    public function shopOptionStatuses()
{
    return $this->hasMany(ShopItemOptionStatus::class, 'item_option_id', 'id');
}
public function getIconUrlAttribute()
{
    if ($this->icon) {
        return url('storage/' . $this->icon);
    }
    return null;
}
}
