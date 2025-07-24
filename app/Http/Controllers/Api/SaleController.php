<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Services\ReceiptService;
use App\Services\ExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    protected $receiptService;
    protected $exportService;

    public function __construct(ReceiptService $receiptService, ExportService $exportService)
    {
        $this->receiptService = $receiptService;
        $this->exportService = $exportService;
    }

    public function index(Request $request)
    {
        $query = Sale::with(['cashier:id,name,username', 'saleItems.product:id,name,code']);

        // Filter by date range
        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('transaction_date', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Filter by period
        if ($request->has('period')) {
            switch ($request->period) {
                case 'today':
                    $query->today();
                    break;
                case 'week':
                    $query->thisWeek();
                    break;
                case 'month':
                    $query->thisMonth();
                    break;
            }
        }

        // Filter by cashier
        if ($request->has('cashier_id')) {
            $query->where('cashier_id', $request->cashier_id);
        }

        // If user is pegawai, only show their sales
        if ($request->user()->isPegawai()) {
            $query->where('cashier_id', $request->user()->id);
        }

        // Search by code
        if ($request->has('search')) {
            $query->where('code', 'like', "%{$request->search}%");
        }

        $sales = $query->orderBy('transaction_date', 'desc')
                      ->paginate($request->get('per_page', 15));

        // Hide revenue data for pegawai
        if ($request->user()->isPegawai()) {
            $sales->getCollection()->transform(function ($sale) {
                $sale->makeHidden(['total', 'subtotal', 'tax', 'discount', 'cash_received', 'change_amount']);
                return $sale;
            });
        }

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'cash_received' => 'nullable|numeric|min:0',
            'payment_method' => 'required|in:cash,card,transfer',
            'notes' => 'nullable|string|max:500',
        ]);

        DB::beginTransaction();
        
        try {
            // Check stock availability for all items
            foreach ($request->items as $item) {
                $product = Product::findOrFail($item['product_id']);
                if (!$product->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => "Product {$product->name} is not active"
                    ], 422);
                }
                if ($product->stock < $item['quantity']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Insufficient stock for product: {$product->name}. Available: {$product->stock}"
                    ], 422);
                }
            }

            // Create sale
            $sale = Sale::create([
                'subtotal' => 0,
                'tax' => $request->get('tax', 0),
                'discount' => $request->get('discount', 0),
                'total' => 0,
                'cash_received' => $request->cash_received,
                'payment_method' => $request->payment_method,
                'notes' => $request->notes,
            ]);

            // Add items to sale
            foreach ($request->items as $item) {
                $sale->addItem(
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'] ?? null,
                    $item['discount'] ?? 0
                );
            }

            // Recalculate totals
            $sale->calculateTotals();

            // Validate payment for cash transactions
            if ($request->payment_method === 'cash' && $request->cash_received) {
                if ($request->cash_received < $sale->total) {
                    DB::rollback();
                    return response()->json([
                        'success' => false,
                        'message' => 'Cash received is less than total amount'
                    ], 422);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale->load([
                    'cashier:id,name,username',
                    'saleItems.product:id,name,code,unit',
                ])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create sale: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, Sale $sale)
    {
        // If user is pegawai and not the cashier, deny access
        if ($request->user()->isPegawai() && $sale->cashier_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $saleData = $sale->load([
            'cashier:id,name,username',
            'saleItems.product:id,name,code,unit',
            'printLogs.printedBy:id,name'
        ]);

        // Hide revenue data for pegawai
        if ($request->user()->isPegawai()) {
            $saleData->makeHidden(['total', 'subtotal', 'tax', 'discount', 'cash_received', 'change_amount']);
        }

        return response()->json([
            'success' => true,
            'data' => $saleData
        ]);
    }

    public function printReceipt(Request $request, Sale $sale)
    {
        $request->validate([
            'printer_name' => 'nullable|string|max:255',
            'is_reprint' => 'boolean',
        ]);

        // If user is pegawai and not the cashier, deny access
        if ($request->user()->isPegawai() && $sale->cashier_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $printLog = $sale->printReceipt(
            $request->printer_name,
            $request->get('is_reprint', false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Receipt printed successfully',
            'data' => [
                'print_log' => $printLog,
                'receipt_text' => $this->receiptService->generateReceiptContent($sale),
                'receipt_json' => $this->receiptService->generateReceiptJson($sale)
            ]
        ]);
    }

    public function getSalesReport(Request $request)
    {
        // Only allow pemilik to access this
        if (!$request->user()->canViewRevenue()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->get('period', 'today');
        $query = Sale::query();

        switch ($period) {
            case 'today':
                $query->today();
                break;
            case 'week':
                $query->thisWeek();
                break;
            case 'month':
                $query->thisMonth();
                break;
        }

        $totalSales = $query->count();
        $totalRevenue = $query->sum('total');
        $averagePerSale = $totalSales > 0 ? $totalRevenue / $totalSales : 0;

        // Payment method breakdown
        $paymentMethods = $query->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total) as total'))
                               ->groupBy('payment_method')
                               ->get();

        // Top selling products
        $topProducts = DB::table('sale_items')
            ->join('products', 'sale_items.product_id', '=', 'products.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->when($period === 'today', function($q) {
                $q->whereDate('sales.transaction_date', today());
            })
            ->when($period === 'week', function($q) {
                $q->whereBetween('sales.transaction_date', [
                    now()->startOfWeek(),
                    now()->endOfWeek()
                ]);
            })
            ->when($period === 'month', function($q) {
                $q->whereMonth('sales.transaction_date', now()->month)
                  ->whereYear('sales.transaction_date', now()->year);
            })
            ->select(
                'products.name',
                'products.code',
                DB::raw('SUM(sale_items.quantity) as total_quantity'),
                DB::raw('SUM(sale_items.total_price) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.code')
            ->orderBy('total_quantity', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'summary' => [
                    'total_sales' => $totalSales,
                    'total_revenue' => $totalRevenue,
                    'average_per_sale' => round($averagePerSale, 2)
                ],
                'payment_methods' => $paymentMethods,
                'top_products' => $topProducts
            ]
        ]);
    }

    public function getRevenueChart(Request $request)
    {
        // Only allow pemilik to access this
        if (!$request->user()->canViewRevenue()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $period = $request->get('period', 'daily');
        $days = $request->get('days', 7);

        $data = [];
        $startDate = now()->subDays($days - 1)->startOfDay();

        for ($i = 0; $i < $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $revenue = Sale::whereDate('transaction_date', $date)->sum('total');
            $salesCount = Sale::whereDate('transaction_date', $date)->count();
            
            $data[] = [
                'date' => $date->format('Y-m-d'),
                'day' => $date->format('D'),
                'revenue' => $revenue,
                'sales_count' => $salesCount
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function exportSales(Request $request)
    {
        // Only allow pemilik to export
        if (!$request->user()->canViewRevenue()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $format = $request->get('format', 'array'); // array, csv

        try {
            if ($format === 'csv') {
                $export = $this->exportService->exportSalesToCsv($startDate, $endDate);
                
                return response($export['content'])
                    ->header('Content-Type', $export['mime_type'])
                    ->header('Content-Disposition', 'attachment; filename="' . $export['filename'] . '"');
            } else {
                $data = $this->exportService->exportSalesToArray($startDate, $endDate);
                
                return response()->json([
                    'success' => true,
                    'message' => 'Export data generated successfully',
                    'data' => $data
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }
}