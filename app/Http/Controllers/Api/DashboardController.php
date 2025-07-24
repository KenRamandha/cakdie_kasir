<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\Category;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $data = [
            'overview' => $this->getOverviewStats($user),
            'recent_sales' => $this->getRecentSales($user),
            'low_stock_products' => $this->getLowStockProducts(),
        ];

        // Only show revenue data to pemilik
        if ($user->canViewRevenue()) {
            $data['revenue_chart'] = $this->getRevenueChart();
            $data['top_products'] = $this->getTopProducts();
        }

        $data['stock_movement_chart'] = $this->getStockMovementChart();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    private function getOverviewStats($user)
    {
        $stats = [
            'total_products' => Product::active()->count(),
            'total_categories' => Category::where('is_active', true)->count(),
            'low_stock_count' => Product::lowStock()->active()->count(),
            'today_sales_count' => Sale::today()->count(),
        ];

        // Only show revenue to pemilik
        if ($user->canViewRevenue()) {
            $stats['today_revenue'] = Sale::today()->sum('total');
            $stats['this_week_revenue'] = Sale::thisWeek()->sum('total');
            $stats['this_month_revenue'] = Sale::thisMonth()->sum('total');
        }

        return $stats;
    }

    private function getRecentSales($user)
    {
        $query = Sale::with(['cashier:id,name', 'saleItems.product:id,name'])
                    ->orderBy('transaction_date', 'desc')
                    ->limit(10);

        // If user is pegawai, only show their sales
        if ($user->isPegawai()) {
            $query->where('cashier_id', $user->id);
        }

        return $query->get()->map(function($sale) use ($user) {
            $saleData = [
                'id' => $sale->id,
                'code' => $sale->code,
                'transaction_date' => $sale->transaction_date->format('d/m/Y H:i'),
                'cashier' => $sale->cashier->name,
                'items_count' => $sale->saleItems->count(),
                'payment_method' => $sale->payment_method,
            ];

            // Only show total to pemilik
            if ($user->canViewRevenue()) {
                $saleData['total'] = $sale->total;
            }

            return $saleData;
        });
    }

    private function getLowStockProducts()
    {
        return Product::with('category:id,name')
                     ->lowStock()
                     ->active()
                     ->orderBy('stock')
                     ->limit(10)
                     ->get(['id', 'name', 'code', 'stock', 'min_stock', 'category_id']);
    }

    private function getRevenueChart()
    {
        $days = 7;
        $data = [];
        $startDate = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $revenue = Sale::whereDate('transaction_date', $date)->sum('total');
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => $revenue
            ];
        }

        return $data;
    }

    private function getStockMovementChart()
    {
        $days = 7;
        $data = [];
        $startDate = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            
            $stockIn = StockLog::whereDate('created_at', $date)
                              ->where('type', 'in')
                              ->sum('quantity');
                              
            $stockOut = StockLog::whereDate('created_at', $date)
                               ->where('type', 'out')
                               ->sum('quantity');
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'stock_in' => $stockIn,
                'stock_out' => $stockOut
            ];
        }

        return $data;
    }

    private function getTopProducts()
    {
        return DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereMonth('sales.transaction_date', now()->month)
            ->whereYear('sales.transaction_date', now()->year)
            ->select(
                'products.name',
                'products.code',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderBy('total_quantity', 'desc')
            ->limit(5)
            ->get();
    }

    public function getStockMovementByCategory(Request $request)
    {
        $period = $request->get('period', 'daily'); // daily, weekly, monthly
        $days = $request->get('days', 7);

        $categories = Category::with(['products.stockLogs' => function($query) use ($days) {
            $query->where('created_at', '>=', now()->subDays($days));
        }])->get();

        $data = $categories->map(function($category) use ($period, $days) {
            $stockIn = 0;
            $stockOut = 0;

            foreach ($category->products as $product) {
                $stockIn += $product->stockLogs->where('type', 'in')->sum('quantity');
                $stockOut += $product->stockLogs->where('type', 'out')->sum('quantity');
            }

            return [
                'category' => $category->name,
                'stock_in' => $stockIn,
                'stock_out' => $stockOut
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function getRevenueByPeriod(Request $request)
    {
        // Only allow pemilik to access this
        if (!$request->user()->canViewRevenue()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->get('period', 'daily'); // daily, weekly, monthly
        $data = [];

        switch ($period) {
            case 'daily':
                $data = $this->getDailyRevenue();
                break;
            case 'weekly':
                $data = $this->getWeeklyRevenue();
                break;
            case 'monthly':
                $data = $this->getMonthlyRevenue();
                break;
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    private function getDailyRevenue()
    {
        $days = 30;
        $data = [];
        $startDate = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $revenue = Sale::whereDate('transaction_date', $date)->sum('total');
            
            $data[] = [
                'period' => $date->format('d/m'),
                'revenue' => $revenue
            ];
        }

        return $data;
    }

    private function getWeeklyRevenue()
    {
        $weeks = 12;
        $data = [];
        $startDate = now()->subWeeks($weeks - 1)->startOfWeek();

        for ($i = 0; $i < $weeks; $i++) {
            $weekStart = $startDate->copy()->addWeeks($i);
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            $revenue = Sale::whereBetween('transaction_date', [$weekStart, $weekEnd])
                          ->sum('total');
            
            $data[] = [
                'period' => 'Week ' . $weekStart->format('W'),
                'revenue' => $revenue
            ];
        }

        return $data;
    }

    private function getMonthlyRevenue()
    {
        $months = 12;
        $data = [];
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        for ($i = 0; $i < $months; $i++) {
            $month = $startDate->copy()->addMonths($i);
            $revenue = Sale::whereMonth('transaction_date', $month->month)
                          ->whereYear('transaction_date', $month->year)
                          ->sum('total');
            
            $data[] = [
                'period' => $month->format('M Y'),
                'revenue' => $revenue
            ];
        }

        return $data;
    }
}