<?php

namespace App\Services;

use App\Models\Sale;

class ReceiptService
{
    public function generateReceiptContent(Sale $sale)
    {
        $companyName = config('app.name', 'POS System');
        $companyAddress = config('pos.company_address', 'Jl. Contoh No. 123');
        $companyPhone = config('pos.company_phone', '021-12345678');
        
        $receipt = "================================================\n";
        $receipt .= "           " . strtoupper($companyName) . "\n";
        $receipt .= "================================================\n";
        $receipt .= $companyAddress . "\n";
        $receipt .= "Telp: " . $companyPhone . "\n";
        $receipt .= "================================================\n\n";
        
        $receipt .= "No. Transaksi: " . $sale->code . "\n";
        $receipt .= "Tanggal: " . $sale->transaction_date->format('d/m/Y H:i:s') . "\n";
        $receipt .= "Kasir: " . $sale->cashier->name . "\n";
        $receipt .= "================================================\n";
        
        foreach ($sale->saleItems as $item) {
            $receipt .= $item->product->name . "\n";
            $receipt .= sprintf(
                "%d x %s = %s\n",
                $item->quantity,
                number_format($item->unit_price, 0, ',', '.'),
                number_format($item->total_price, 0, ',', '.')
            );
            
            if ($item->discount > 0) {
                $receipt .= "   Diskon: -" . number_format($item->discount, 0, ',', '.') . "\n";
            }
            $receipt .= "\n";
        }
        
        $receipt .= "================================================\n";
        $receipt .= sprintf("Subtotal: %s\n", number_format($sale->subtotal, 0, ',', '.'));
        
        if ($sale->discount > 0) {
            $receipt .= sprintf("Diskon: -%s\n", number_format($sale->discount, 0, ',', '.'));
        }
        
        if ($sale->tax > 0) {
            $receipt .= sprintf("Pajak: %s\n", number_format($sale->tax, 0, ',', '.'));
        }
        
        $receipt .= "================================================\n";
        $receipt .= sprintf("TOTAL: %s\n", number_format($sale->total, 0, ',', '.'));
        $receipt .= "================================================\n";
        
        if ($sale->payment_method === 'cash' && $sale->cash_received) {
            $receipt .= sprintf("Tunai: %s\n", number_format($sale->cash_received, 0, ',', '.'));
            $receipt .= sprintf("Kembali: %s\n", number_format($sale->change_amount, 0, ',', '.'));
        } else {
            $receipt .= "Pembayaran: " . ucfirst($sale->payment_method) . "\n";
        }
        
        $receipt .= "================================================\n";
        $receipt .= "         TERIMA KASIH ATAS KUNJUNGAN ANDA\n";
        $receipt .= "           BARANG YANG SUDAH DIBELI\n";
        $receipt .= "              TIDAK DAPAT DIKEMBALIKAN\n";
        $receipt .= "================================================\n";
        
        return $receipt;
    }

    public function generateReceiptJson(Sale $sale)
    {
        return [
            'company' => [
                'name' => config('app.name', 'POS System'),
                'address' => config('pos.company_address', 'Jl. Contoh No. 123'),
                'phone' => config('pos.company_phone', '021-12345678'),
            ],
            'transaction' => [
                'code' => $sale->code,
                'date' => $sale->transaction_date->format('d/m/Y H:i:s'),
                'cashier' => $sale->cashier->name,
            ],
            'items' => $sale->saleItems->map(function($item) {
                return [
                    'name' => $item->product->name,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total_price' => $item->total_price,
                    'discount' => $item->discount,
                    'unit' => $item->product->unit,
                ];
            }),
            'summary' => [
                'subtotal' => $sale->subtotal,
                'discount' => $sale->discount,
                'tax' => $sale->tax,
                'total' => $sale->total,
                'payment_method' => $sale->payment_method,
                'cash_received' => $sale->cash_received,
                'change_amount' => $sale->change_amount,
            ]
        ];
    }
}