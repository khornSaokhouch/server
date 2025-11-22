<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class CategoryShop extends Pivot
{
    protected $table = 'category_shop';

    protected $fillable = [
        'shop_id',
        'category_id',
        'status',
    ];

    /**
     * Optional: If you want to use Eloquent timestamps
     */
    public $timestamps = true;

    // Relationships
    public function shop()
    {
          return $this->hasMany(Shop::class, 'parent_id');
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)
            ->withPivot('status')
            ->wherePivot('status', 1); // Only show status = 1
    }
    

    public function getImageCategoryUrlAttribute()
    {
        if ($this->image_category) {
            // If you store images in 'public' disk
            return asset('storage/' . $this->image_category);
        }
        return null;
    }
}
