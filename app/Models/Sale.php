<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; 
use Illuminate\Database\Eloquent\Relations\HasMany; 

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'subtotal',
        'tax',
        'discount',
        'total',
        'cash_received',
        'change_amount',
        'payment_method',
        'notes',
        'cashier_id',
        'customer_id',
        'transaction_date'
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'change_amount' => 'decimal:2',
        'transaction_date' => 'datetime',
    ];

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id', 'user_id');
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id', 'code');
    }

    public function printLogs(): HasMany
    {
        return $this->hasMany(PrintLog::class, 'sale_id', 'code');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }
}