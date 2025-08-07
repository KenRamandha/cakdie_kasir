<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StockLog;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Exports\SalesExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Validation\ValidationException;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data dashboard: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    private function getRevenueChart(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil data revenue chart: ' . $e->getMessage());
        }
    }

    private function getStockChart(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil data stock chart: ' . $e->getMessage());
        }
    }

    private function getSalesHistory(Request $request)
    {
        try {
            return Sale::with(['cashier', 'saleItems.product'])
                ->orderBy('transaction_date', 'desc')
                ->paginate(20);
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil history penjualan: ' . $e->getMessage());
        }
    }

    private function getLowStockProducts()
    {
        try {
            return Product::with('category')
                ->whereRaw('stock <= min_stock')
                ->where('is_active', true)
                ->get();
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil data produk stok rendah: ' . $e->getMessage());
        }
    }

    private function getSummaryStats()
    {
        try {
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
        } catch (\Exception $e) {
            throw new \Exception('Gagal mengambil statistik summary: ' . $e->getMessage());
        }
    }

    public function exportSales(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->isPegawai()) {
                return response()->json([
                    'message' => 'Anda tidak memiliki izin untuk mengakses fitur ini',
                    'errors' => [
                        'authorization' => ['Anda tidak memiliki izin untuk mengakses fitur ini']
                    ]
                ], 403);
            }

            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'format' => 'sometimes|in:xlsx,csv,pdf'
            ]);

            $format = $request->format ?? 'xlsx';
            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            $query = Sale::with(['cashier', 'saleItems.product'])
                ->whereBetween('transaction_date', [$startDate, $endDate])
                ->orderBy('transaction_date', 'desc');

            $sales = $query->get();
            $filename = 'sales_export_' . date('Y-m-d') . '_' . $startDate->format('Y-m-d') . '_to_' . $endDate->format('Y-m-d');

            switch ($format) {
                case 'csv':
                    return Excel::download(new SalesExport($sales), "$filename.csv", \Maatwebsite\Excel\Excel::CSV);
                case 'pdf':
                    return Excel::download(new SalesExport($sales), "$filename.pdf", \Maatwebsite\Excel\Excel::DOMPDF);
                default:
                    return Excel::download(new SalesExport($sales), "$filename.xlsx");
            }
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengekspor data penjualan: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function checkExportSize(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
            ]);

            $startDate = Carbon::parse($request->start_date)->startOfDay();
            $endDate = Carbon::parse($request->end_date)->endOfDay();

            $count = Sale::whereBetween('transaction_date', [$startDate, $endDate])->count();

            return response()->json(['count' => $count]);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memeriksa ukuran ekspor: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}