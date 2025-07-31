<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StockLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\SalesExport;
use Maatwebsite\Excel\Facades\Excel;

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

        $query = StockLog::with(['product' => function ($query) {
            $query->with('category');
        }]);

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
            if (!$data->product || !$data->product->category) {
                continue;
            }

            $categoryCode = $data->product->category->code;
            $categoryName = $data->product->category->name;

            if (!isset($categorizedData[$categoryCode])) {
                $categorizedData[$categoryCode] = [
                    'name' => $categoryName,
                    'in' => 0,
                    'out' => 0,
                    'adjustment' => 0,
                ];
            }

            $categorizedData[$categoryCode][$data->type] += (float) $data->total_quantity;
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

        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'format' => 'sometimes|in:xlsx,csv,pdf'
        ]);

        $format = $request->format ?? 'xlsx';
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $query = Sale::with(['cashier', 'saleItems.product'])
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->orderBy('transaction_date', 'desc');

        $sales = $query->get();
        $filename = 'sales_export_' . date('Y-m-d') . '_' . $startDate . '_to_' . $endDate;

        switch ($format) {
            case 'csv':
                return Excel::download(new SalesExport($sales), "$filename.csv", \Maatwebsite\Excel\Excel::CSV);
            case 'pdf':
                return Excel::download(new SalesExport($sales), "$filename.pdf", \Maatwebsite\Excel\Excel::DOMPDF);
            default:
                return Excel::download(new SalesExport($sales), "$filename.xlsx");
        }
    }

    public function checkExportSize(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $count = Sale::whereBetween('transaction_date', [
            $request->start_date,
            $request->end_date
        ])->count();

        return response()->json(['count' => $count]);
    }
}
