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
        
        if ($user->isPegawai()) {
            return response()->json([
                'stock_chart' => $this->getStockChart($request),
                'sales_history' => $this->getSalesHistory($request),
                'low_stock_products' => $this->getLowStockProducts(),
            ]);
        }

        return response()->json([
            'revenue_chart' => $this->getRevenueChart($request),
            'stock_chart' => $this->getStockChart($request),
            'sales_history' => $this->getSalesHistory($request),
            'low_stock_products' => $this->getLowStockProducts(),
            'summary_stats' => $this->getSummaryStats(),
        ]);
    }

    private function getRevenueChart(Request $request)
    {
        $period = $request->get('period', 'daily'); 
        
        $query = Sale::query();
        
        switch ($period) {
            case 'weekly':
                $query->selectRaw('YEARWEEK(transaction_date) as period, SUM(total) as revenue')
                      ->where('transaction_date', '>=', Carbon::now()->subWeeks(8))
                      ->groupByRaw('YEARWEEK(transaction_date)')
                      ->orderByRaw('YEARWEEK(transaction_date)');
                break;
            case 'monthly':
                $query->selectRaw('YEAR(transaction_date) as year, MONTH(transaction_date) as month, SUM(total) as revenue')
                      ->where('transaction_date', '>=', Carbon::now()->subMonths(12))
                      ->groupByRaw('YEAR(transaction_date), MONTH(transaction_date)')
                      ->orderByRaw('YEAR(transaction_date), MONTH(transaction_date)');
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
        
        $query = StockLog::with(['product.category'])
                         ->select([
                             'product_id',
                             'type',
                             'created_at',
                             DB::raw('SUM(quantity) as total_quantity')
                         ]);
        
        switch ($period) {
            case 'weekly':
                $query->selectRaw('YEARWEEK(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subWeeks(8))
                      ->groupBy(['period', 'product_id', 'type']);
                break;
            case 'monthly':
                $query->selectRaw('YEAR(created_at) as year, MONTH(created_at) as month, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subMonths(12))
                      ->groupBy(['year', 'month', 'product_id', 'type']);
                break;
            default: 
                $query->selectRaw('DATE(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                      ->where('created_at', '>=', Carbon::now()->subDays(30))
                      ->groupBy(['period', 'product_id', 'type']);
        }
        
        $stockData = $query->get();
        
        // Group by category
        $categorizedData = [];
        foreach ($stockData as $data) {
            $categoryName = $data->product->category->name;
            if (!isset($categorizedData[$categoryName])) {
                $categorizedData[$categoryName] = [
                    'in' => 0,
                    'out' => 0,
                ];
            }
            $categorizedData[$categoryName][$data->type] += $data->total_quantity;
        }
        
        return $categorizedData;
    }

    private function getSalesHistory(Request $request)
    {
        return Sale::with(['cashier', 'saleItems.product'])
                   ->orderBy('transaction_date', 'desc')
                   ->paginate(20);
    }

    private function getLowStockProducts()
    {
        return Product::with('category')
                      ->whereRaw('stock <= min_stock')
                      ->where('is_active', true)
                      ->get();
    }

    private function getSummaryStats()
    {
        $today = Carbon::today();
        $thisMonth = Carbon::now()->startOfMonth();
        
        return [
            'today_sales' => Sale::whereDate('transaction_date', $today)->sum('total'),
            'today_transactions' => Sale::whereDate('transaction_date', $today)->count(),
            'month_sales' => Sale::where('transaction_date', '>=', $thisMonth)->sum('total'),
            'month_transactions' => Sale::where('transaction_date', '>=', $thisMonth)->count(),
            'total_products' => Product::where('is_active', true)->count(),
            'low_stock_count' => Product::whereRaw('stock <= min_stock')->where('is_active', true)->count(),
        ];
    }

    public function exportSales(Request $request)
    {
        $user = $request->user();
        
        if ($user->isPegawai()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        
        $query = Sale::with(['cashier', 'saleItems.product'])
                     ->orderBy('transaction_date', 'desc');
                     
        if ($startDate && $endDate) {
            $query->whereBetween('transaction_date', [$startDate, $endDate]);
        }
        
        $sales = $query->get();

        $filename = 'sales_export_' . date('Y-m-d') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($sales) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['Kode Transaksi', 'Tanggal', 'Kasir', 'Subtotal', 'Pajak', 'Diskon', 'Total', 'Metode Pembayaran', 'Catatan']);
            
            foreach ($sales as $sale) {
                fputcsv($file, [
                    $sale->code,
                    $sale->transaction_date->format('Y-m-d H:i:s'),
                    $sale->cashier->name,
                    $sale->subtotal,
                    $sale->tax,
                    $sale->discount,
                    $sale->total,
                    $sale->payment_method,
                    $sale->notes
                ]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}