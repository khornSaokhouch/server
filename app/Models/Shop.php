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
    ];

    public function categories()
    {
        return $this->hasMany(Category::class);
    }
}
