<?php
namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function salesReport(Request $request)
    {
        if ($request->user()->role !== 'pemilik') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

        $report = Sale::with('saleItems.product')
                     ->whereBetween('transaction_date', [$startDate, $endDate])
                     ->get();

        $summary = [
            'total_transactions' => $report->count(),
            'total_revenue' => $report->sum('total'),
            'total_discount' => $report->sum('discount'),
            'total_tax' => $report->sum('tax'),
            'average_transaction' => $report->avg('total'),
            'payment_methods' => $report->groupBy('payment_method')
                                       ->map->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'transactions' => $report,
        ]);
    }

    public function topProducts(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::now()->startOfMonth());
        $endDate = $request->get('end_date', Carbon::now()->endOfMonth());

        $topProducts = SaleItem::select('product_id', 
                                       DB::raw('SUM(quantity) as total_sold'),
                                       DB::raw('SUM(total_price) as total_revenue'))
                              ->with('product.category')
                              ->whereHas('sale', function($query) use ($startDate, $endDate) {
                                  $query->whereBetween('transaction_date', [$startDate, $endDate]);
                              })
                              ->groupBy('product_id')
                              ->orderBy('total_sold', 'desc')
                              ->limit(10)
                              ->get();

        return response()->json($topProducts);
    }
}