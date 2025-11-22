<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'location',
        'status',
        'owner_user_id',
        'latitude',
        'longitude',
        'image',
        'open_time',
        'close_time'
    ];
    protected $appends = ['image_url']; // 👈 this ensures it's included in JSON


    public function categories()
    {
        return $this->belongsToMany(Category::class, 'category_shop')
                ->withPivot('status')
                ->withTimestamps();
    }
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }
 

    public function shops()
    {
        return $this->hasMany(Shop::class, 'owner_user_id');
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }

    

}
