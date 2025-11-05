<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'category_id',
        'name',
        'description',
        'price_cents',
        'image_url',
        'is_available',
        'display_order',
    ];

    /**
     * Relationship: Item belongs to a Shop
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Relationship: Item belongs to a Category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Accessor for full image URL
     */
    public function getImageUrlAttribute($value)
    {
        if (!$value) {
            return null; // or default image URL
        }
        return url('storage/' . $value);
    }

    /**
     * Optional: Mutator to store only the path
     */
    public function setImageUrlAttribute($value)
    {
        // If uploaded file, store it in storage and save path
        if (request()->hasFile('image_url')) {
            $this->attributes['image_url'] = request()->file('image_url')->store('items', 'public');
        } else {
            $this->attributes['image_url'] = $value;
        }
    }
}
