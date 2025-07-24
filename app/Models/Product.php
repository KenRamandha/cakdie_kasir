<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'price',
        'cost_price',
        'stock',
        'min_stock',
        'unit',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'stock' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($product) {
            $product->code = $product->generateCode();
            $product->created_by = Auth::id();
        });

        static::updating(function ($product) {
            $product->updated_by = Auth::id();
        });
    }

    // Relationships
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockLogs()
    {
        return $this->hasMany(StockLog::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Helper methods
    public function generateCode()
    {
        $prefix = 'PRD';
        $lastProduct = static::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastProduct) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastProduct->code, 3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function isLowStock()
    {
        return $this->stock <= $this->min_stock;
    }

    public function updateStock($quantity, $type = 'out', $notes = null, $referenceType = null, $referenceId = null)
    {
        $stockBefore = $this->stock;
        
        if ($type === 'in') {
            $this->stock += $quantity;
        } elseif ($type === 'out') {
            $this->stock -= $quantity;
        } else { // adjustment
            $this->stock = $quantity;
        }
        
        $this->save();

        // Create stock log
        StockLog::create([
            'code' => $this->generateStockLogCode(),
            'product_id' => $this->id,
            'type' => $type,
            'quantity' => $quantity,
            'stock_before' => $stockBefore,
            'stock_after' => $this->stock,
            'notes' => $notes,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => Auth::id(),
        ]);

        return $this;
    }

    private function generateStockLogCode()
    {
        $prefix = 'STK' . date('Ymd');
        $lastLog = StockLog::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastLog) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastLog->code, -3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('stock <= min_stock');
    }
}