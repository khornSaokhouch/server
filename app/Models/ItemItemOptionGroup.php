<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemItemOptionGroup extends Model
{
    use HasFactory;

    protected $table = 'item_item_option_group';

    protected $fillable = [
        'item_id',
        'item_option_group_id',
    ];

    // Relationship: pivot belongs to an item
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Relationship: pivot belongs to an option group
    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_item_option_group')
                    ->withTimestamps(); // optional, if you need pivot timestamps here
    }
    
}
