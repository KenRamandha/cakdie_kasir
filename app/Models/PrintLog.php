<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'sale_id',
        'printed_by',
        'printed_at',
        'printer_name',
        'print_type',
        'is_reprint',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'is_reprint' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($printLog) {
            $printLog->printed_at = now();
        });
    }

    
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}