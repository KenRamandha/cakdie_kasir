<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($category) {
            $category->code = $category->generateCode();
            $category->created_by = Auth::id();
        });

        static::updating(function ($category) {
            $category->updated_by = Auth::id();
        });
    }

    // Relationships
    public function products()
    {
        return $this->hasMany(Product::class);
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
        $prefix = 'CAT';
        $lastCategory = static::where('code', 'like', $prefix . '%')
            ->orderBy('code', 'desc')
            ->first();
        
        if (!$lastCategory) {
            return $prefix . '001';
        }
        
        $lastNumber = intval(substr($lastCategory->code, 3));
        return $prefix . str_pad($lastNumber + 1, 3, '0', STR_PAD_LEFT);
    }

    public function getActiveProductsCount()
    {
        return $this->products()->where('is_active', true)->count();
    }
}