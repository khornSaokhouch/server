<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'firebase_uid',
        'name',
        'email',
        'phone',
        'password',
        'role',
        'profile_image', // ✅ optional: for Firebase/Google photo
    ];
    protected $appends = ['image_url']; // 👈 automatically included in JSON
    /**
     * The attributes that should be hidden for arrays.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * JWT Identifier.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * JWT Custom Claims.
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * Role check helpers.
     */
       /**
     * Check if the user is an owner.
     *
     * @return bool
     */

    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isCustomer()
    {
        return $this->role === 'customer';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function shops()
    {
        return $this->hasMany(Shop::class, 'owner_user_id');
    }

    public function getImageUrlAttribute()
    {
        if ($this->profile_image) {
            return asset('storage/' . $this->profile_image);
        }
        return null;
    }
    

}
