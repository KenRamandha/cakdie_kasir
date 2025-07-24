<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

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
        'transaction_date',
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

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($sale) {
            $sale->code = $sale->generateCode();
            $sale->cashier_id = Auth::id();
            $sale->transaction_date = now();
        });
    }

    // Relationships
    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function printLogs()
    {
        return $this->hasMany(PrintLog::class);
    }

    // Helper methods
    public function generateCode()
    {
        $prefix = 'SL' . date('Ymd');
        $lastSale = static::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastSale) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastSale->code, -3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function calculateTotals()
    {
        $this->subtotal = $this->saleItems->sum('total_price');
        $this->total = $this->subtotal + $this->tax - $this->discount;
        
        if ($this->cash_received) {
            $this->change_amount = $this->cash_received - $this->total;
        }
        
        $this->save();
        return $this;
    }

    public function addItem($productId, $quantity, $unitPrice = null, $discount = 0)
    {
        $product = Product::findOrFail($productId);
        
        if (!$unitPrice) {
            $unitPrice = $product->price;
        }

        $totalPrice = ($unitPrice * $quantity) - $discount;

        // Create sale item
        $saleItem = SaleItem::create([
            'code' => $this->generateSaleItemCode(),
            'sale_id' => $this->id,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $totalPrice,
            'discount' => $discount,
        ]);

        // Update product stock
        $product->updateStock($quantity, 'out', 'Sold via sale: ' . $this->code, 'sale', $this->id);

        $this->calculateTotals();
        
        return $saleItem;
    }

    private function generateSaleItemCode()
    {
        $prefix = 'SI' . date('Ymd');
        $lastItem = SaleItem::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastItem) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastItem->code, -3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function printReceipt($printerName = null, $isReprint = false)
    {
        return PrintLog::create([
            'code' => $this->generatePrintLogCode(),
            'sale_id' => $this->id,
            'printed_by' => Auth::id(),
            'printer_name' => $printerName,
            'print_type' => 'receipt',
            'is_reprint' => $isReprint,
        ]);
    }

    private function generatePrintLogCode()
    {
        $prefix = 'PL' . date('Ymd');
        $lastLog = PrintLog::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastLog) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastLog->code, -3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('transaction_date', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('transaction_date', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('transaction_date', now()->month)
                    ->whereYear('transaction_date', now()->year);
    }
}