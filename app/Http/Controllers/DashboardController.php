<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StockLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
                $query->selectRaw("
                STR_TO_DATE(CONCAT(YEAR(transaction_date), ' ', WEEK(transaction_date, 3), ' 1'), '%X %V %w') as period,
                SUM(total) as revenue
            ")
                    ->where('transaction_date', '>=', Carbon::now()->subWeeks(8))
                    ->groupByRaw("STR_TO_DATE(CONCAT(YEAR(transaction_date), ' ', WEEK(transaction_date, 3), ' 1'), '%X %V %w')")
                    ->orderByRaw("STR_TO_DATE(CONCAT(YEAR(transaction_date), ' ', WEEK(transaction_date, 3), ' 1'), '%X %V %w')");
                break;

            case 'monthly':
                $query->selectRaw("
                DATE_FORMAT(transaction_date, '%Y-%m-01') as period,
                SUM(total) as revenue
            ")
                    ->where('transaction_date', '>=', Carbon::now()->subMonths(12))
                    ->groupByRaw("DATE_FORMAT(transaction_date, '%Y-%m-01')")
                    ->orderByRaw("DATE_FORMAT(transaction_date, '%Y-%m-01')");
                break;

            default:
                $query->selectRaw('DATE(transaction_date) as period, SUM(total) as revenue')
                    ->where('transaction_date', '>=', Carbon::now()->subDays(30))
                    ->groupByRaw('DATE(transaction_date)')
                    ->orderByRaw('DATE(transaction_date)');
        }

        return $query->get()->map(function ($item) {
            return [
                'period' => (string) $item->period,
                'revenue' => (float) $item->revenue,
            ];
        });
    }



    private function getStockChart(Request $request)
    {
        $period = $request->get('period', 'daily');

        $query = StockLog::with(['product.category']);

        switch ($period) {
            case 'weekly':
                $query->selectRaw("
                STR_TO_DATE(CONCAT(YEAR(created_at), ' ', WEEK(created_at, 3), ' 1'), '%X %V %w') as period,
                product_id, type, SUM(quantity) as total_quantity
            ")
                    ->where('created_at', '>=', Carbon::now()->subWeeks(8))
                    ->groupByRaw("STR_TO_DATE(CONCAT(YEAR(created_at), ' ', WEEK(created_at, 3), ' 1'), '%X %V %w'), product_id, type")
                    ->orderByRaw("STR_TO_DATE(CONCAT(YEAR(created_at), ' ', WEEK(created_at, 3), ' 1'), '%X %V %w')");
                break;

            case 'monthly':
                $query->selectRaw("
                DATE_FORMAT(created_at, '%Y-%m-01') as period,
                product_id, type, SUM(quantity) as total_quantity
            ")
                    ->where('created_at', '>=', Carbon::now()->subMonths(12))
                    ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m-01'), product_id, type")
                    ->orderByRaw("DATE_FORMAT(created_at, '%Y-%m-01')");
                break;

            default:
                $query->selectRaw('DATE(created_at) as period, product_id, type, SUM(quantity) as total_quantity')
                    ->where('created_at', '>=', Carbon::now()->subDays(30))
                    ->groupByRaw('DATE(created_at), product_id, type')
                    ->orderByRaw('DATE(created_at)');
        }

        $stockData = $query->get();

        $categorizedData = [];
        foreach ($stockData as $data) {
            $categoryName = $data->product->category->name;

            if (!isset($categorizedData[$categoryName])) {
                $categorizedData[$categoryName] = [
                    'in' => 0,
                    'out' => 0,
                    'adjustment' => 0,
                ];
            }

            $categorizedData[$categoryName][$data->type] = (float) ($categorizedData[$categoryName][$data->type] ?? 0) + (float) $data->total_quantity;
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
            'today_sales' => (float)(Sale::whereDate('transaction_date', $today)->sum('total') ?? 0),
            'today_transactions' => (int)(Sale::whereDate('transaction_date', $today)->count() ?? 0),
            'month_sales' => (float)(Sale::where('transaction_date', '>=', $thisMonth)->sum('total') ?? 0),
            'month_transactions' => (int)(Sale::where('transaction_date', '>=', $thisMonth)->count() ?? 0),
            'total_products' => (int)(Product::where('is_active', true)->count() ?? 0),
            'low_stock_count' => (int)(Product::whereRaw('stock <= min_stock')->where('is_active', true)->count() ?? 0),
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

        $callback = function () use ($sales) {
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
