<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'name',
        'image_category',
        'display_order',
    ];

    // Add this accessor
    protected $appends = ['image_category_url'];

    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    // Accessor to return full URL
    public function getImageCategoryUrlAttribute()
    {
        if ($this->image_category) {
            // If you store images in 'public' disk
            return asset('storage/' . $this->image_category);
        }
        return null;
    }
}
