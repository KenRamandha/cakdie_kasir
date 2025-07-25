<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'user_id',
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($user) {
            if (empty($user->user_id)) {
                $latestId = static::max('id') ?? 0;
                $nextId = $latestId + 1;
                $user->user_id = 'USR-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
            }
        });
    }


    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function sales()
    {
        return $this->hasMany(Sale::class, 'cashier_id', 'user_id');
    }

    public function createdProducts()
    {
        return $this->hasMany(Product::class, 'created_by');
    }
}
