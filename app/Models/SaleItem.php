<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'sale_id',
        'product_id',
        'quantity',
        'unit_price',
        'total_price',
        'discount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'discount' => 'decimal:2',
    ];

    
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    
    public function calculateTotal()
    {
        $this->total_price = ($this->unit_price * $this->quantity) - $this->discount;
        $this->save();
        return $this;
    }
}