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
        'icon'
    ];

    /**
     * Relationship: Option belongs to a group
     */
    public function group()
    {
        return $this->belongsTo(ItemOptionGroup::class, 'item_option_group_id');
    }
}
