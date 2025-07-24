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
        'name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function sales()
    {
        return $this->hasMany(Sale::class, 'cashier_id');
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class, 'created_by');
    }

    public function printLogs()
    {
        return $this->hasMany(PrintLog::class, 'printed_by');
    }

    
    public function isPemilik()
    {
        return $this->role === 'pemilik';
    }

    public function isPegawai()
    {
        return $this->role === 'pegawai';
    }

    public function canViewRevenue()
    {
        return $this->role === 'pemilik';
    }
}