<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Customer extends Model
{
    protected $primaryKey = 'customer_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'customer_id',
        'phone',
        'name',
        'purchase_count',
        'last_purchase_at'
    ];

    protected $casts = [
        'last_purchase_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            $model->customer_id = 'CUST-' . Str::upper(Str::random(8));
        });
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id', 'customer_id');
    }
}