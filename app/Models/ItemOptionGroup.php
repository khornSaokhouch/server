<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemOptionGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'is_required',
    ];

    /**
     * Relationship: An option group belongs to an item
     */
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    /**
     * Relationship: An option group has many options
     */
    // public function options()
    // {
    //     return $this->hasMany(ItemOption::class);
    // }


    public function items()
{
    return $this->belongsToMany(Item::class, 'item_item_option_group');
}
public function options()
    {
        return $this->hasMany(ItemOption::class, 'item_option_group_id');
    }

}
