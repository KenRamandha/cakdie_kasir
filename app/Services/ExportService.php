<?php

namespace App\Services;

use App\Models\Sale;
use Illuminate\Support\Collection;

class ExportService
{
    public function exportSalesToArray($startDate = null, $endDate = null)
    {
        $query = Sale::with(['cashier:id,name', 'saleItems.product:id,name,code,category_id', 'saleItems.product.category:id,name']);

        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $sales = $query->orderBy('transaction_date', 'desc')->get();

        $exportData = [];
        
        // Header
        $exportData[] = [
            'No. Transaksi',
            'Tanggal',
            'Kasir',
            'Nama Produk',
            'Kategori',
            'Kode Produk',
            'Qty',
            'Harga Satuan',
            'Diskon Item',
            'Total Item',
            'Subtotal',
            'Diskon Transaksi',
            'Pajak',
            'Total Transaksi',
            'Metode Pembayaran',
            'Uang Diterima',
            'Kembalian',
            'Catatan'
        ];

        foreach ($sales as $sale) {
            foreach ($sale->saleItems as $index => $item) {
                $row = [
                    $index === 0 ? $sale->code : '', // Only show transaction code on first item
                    $index === 0 ? $sale->transaction_date->format('d/m/Y H:i:s') : '',
                    $index === 0 ? $sale->cashier->name : '',
                    $item->product->name,
                    $item->product->category->name,
                    $item->product->code,
                    $item->quantity,
                    number_format($item->unit_price, 2),
                    number_format($item->discount, 2),
                    number_format($item->total_price, 2),
                    $index === 0 ? number_format($sale->subtotal, 2) : '',
                    $index === 0 ? number_format($sale->discount, 2) : '',
                    $index === 0 ? number_format($sale->tax, 2) : '',
                    $index === 0 ? number_format($sale->total, 2) : '',
                    $index === 0 ? ucfirst($sale->payment_method) : '',
                    $index === 0 ? number_format($sale->cash_received ?? 0, 2) : '',
                    $index === 0 ? number_format($sale->change_amount ?? 0, 2) : '',
                    $index === 0 ? $sale->notes : '',
                ];
                
                $exportData[] = $row;
            }
        }

        return $exportData;
    }

    public function exportSalesToCsv($startDate = null, $endDate = null)
    {
        $data = $this->exportSalesToArray($startDate, $endDate);
        
        $filename = 'sales_export_' . date('Y-m-d_H-i-s') . '.csv';
        $handle = fopen('php://temp', 'w');
        
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);
        
        return [
            'filename' => $filename,
            'content' => $csv,
            'mime_type' => 'text/csv'
        ];
    }

    public function exportProductsToArray()
    {
        $products = \App\Models\Product::with(['category:id,name', 'createdBy:id,name'])
                                     ->orderBy('name')
                                     ->get();

        $exportData = [];
        
        // Header
        $exportData[] = [
            'Kode Produk',
            'Nama Produk',
            'Kategori',
            'Harga Jual',
            'Harga Beli',
            'Stok',
            'Stok Minimum',
            'Satuan',
            'Status',
            'Dibuat Oleh',
            'Tanggal Dibuat',
            'Deskripsi'
        ];

        foreach ($products as $product) {
            $exportData[] = [
                $product->code,
                $product->name,
                $product->category->name,
                number_format($product->price, 2),
                number_format($product->cost_price ?? 0, 2),
                $product->stock,
                $product->min_stock,
                $product->unit,
                $product->is_active ? 'Aktif' : 'Tidak Aktif',
                $product->createdBy->name ?? 'System',
                $product->created_at->format('d/m/Y H:i:s'),
                $product->description ?? ''
            ];
        }

        return $exportData;
    }

    public function exportStockLogsToArray($startDate = null, $endDate = null)
    {
        $query = \App\Models\StockLog::with(['product:id,name,code', 'product.category:id,name', 'createdBy:id,name']);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [
                $startDate . ' 00:00:00',
                $endDate . ' 23:59:59'
            ]);
        }

        $stockLogs = $query->orderBy('created_at', 'desc')->get();

        $exportData = [];
        
        $exportData[] = [
            'Kode Log',
            'Tanggal',
            'Produk',
            'Kategori',
            'Kode Produk',
            'Tipe',
            'Jumlah',
            'Stok Sebelum',
            'Stok Sesudah',
            'Catatan',
            'Referensi',
            'Dibuat Oleh'
        ];

        foreach ($stockLogs as $log) {
            $exportData[] = [
                $log->code,
                $log->created_at->format('d/m/Y H:i:s'),
                $log->product->name,
                $log->product->category->name,
                $log->product->code,
                ucfirst($log->type),
                $log->quantity,
                $log->stock_before,
                $log->stock_after,
                $log->notes ?? '',
                $log->reference_type ? $log->reference_type . ':' . $log->reference_id : '',
                $log->createdBy->name
            ];
        }

        return $exportData;
    }
}