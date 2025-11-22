<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image_category',
        'status',
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

    public function itemOwners()
    {
        return $this->hasMany(\App\Models\ItemOwner::class, 'category_id');
    }
    public function shops()
{
    return $this->belongsToMany(Shop::class, 'category_shop')
                ->withPivot('status')
                ->withTimestamps();
}

}
