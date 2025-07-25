<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StockLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Pegawai tidak boleh melihat data pendapatan
        if ($user->role === 'pegawai') {
            return response()->json([
                'stock_chart' => $this->getStockChart($request),
                'sales_history' => $this->getSalesHistory($request),
            ]);
        }

        return response()->json([
            'revenue_chart' => $this->getRevenueChart($request),
            'stock_chart' => $this->getStockChart($request),
            'sales_history' => $this->getSalesHistory($request),
        ]);
    }

    private function getRevenueChart(Request $request)
    {
        $period = $request->get('period', 'daily'); // daily, weekly, monthly
        
        $query = Sale::query();
        
        switch ($period) {
            case 'weekly':
                $query->selectRaw('WEEK(transaction_date) as period, SUM(total) as revenue')
                      ->where('transaction_date', '>=', Carbon::now()->subWeeks(8))
                      ->groupByRaw('WEEK(transaction_date)')
                      ->orderByRaw('WEEK(transaction_date)');
                break;
            case 'monthly':
                $query->selectRaw('MONTH(transaction_date) as period, SUM(total) as revenue')
                      ->where('transaction_date', '>=', Carbon::now()->subMonths(12))
                      ->groupByRaw('MONTH(transaction_date)')
                      ->orderByRaw('MONTH(transaction_date)');
                break;
            default: // daily
                $query->selectRaw('DATE(transaction_date) as period, SUM(total) as revenue')
                      ->where('transaction_date', '>=', Carbon::now()->subDays(30))
                      ->groupByRaw('DATE(transaction_date)')
                      ->orderByRaw('DATE(transaction_date)');
        }
        
        return $query->get();
    }

    private function getStockChart(Request $request)
    {
        $period = $request->get('period', 'daily');
        
        $query = StockLog::with('product.category');
        
        switch ($period) {
            case 'weekly':
                $query->selectRaw('WEEK(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subWeeks(8))
                      ->groupBy(['period', 'product_id', 'type']);
                break;
            case 'monthly':
                $query->selectRaw('MONTH(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subMonths(12))
                      ->groupBy(['period', 'product_id', 'type']);
                break;
            default: // daily
                $query->selectRaw('DATE(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subDays(30))
                      ->groupBy(['period', 'product_id', 'type']);
        }
        
        return $query->get();
    }

    private function getSalesHistory(Request $request)
    {
        return Sale::with(['cashier', 'saleItems.product'])
                   ->orderBy('transaction_date', 'desc')
                   ->paginate(20);
    }

    public function exportSales(Request $request)
    {
        $sales = Sale::with(['cashier', 'saleItems.product'])
                     ->orderBy('transaction_date', 'desc')
                     ->get();

        // Simple CSV export
        $filename = 'sales_export_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($sales) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Kode', 'Tanggal', 'Kasir', 'Total', 'Metode Pembayaran']);
            
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->code,
                    $sale->transaction_date->format('Y-m-d H:i:s'),
                    $sale->cashier->name,
                    $sale->total,
                    $sale->payment_method
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}