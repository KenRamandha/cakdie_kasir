<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Traits\ChecksPermissions;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportController extends Controller
{
    use ChecksPermissions;

    public function salesReport(Request $request)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()))->startOfDay();
            $endDate = Carbon::parse($request->get('end_date', Carbon::now()->endOfMonth()))->endOfDay();

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
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melihat laporan penjualan',
                'errors' => [
                    'permission' => ['Anda tidak memiliki izin yang diperlukan']
                ]
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil laporan penjualan: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function topProducts(Request $request)
    {
        try {
            $this->checkOwnerPermission($request->user());

            $startDate = Carbon::parse($request->get('start_date', Carbon::now()->startOfMonth()))->startOfDay();
            $endDate = Carbon::parse($request->get('end_date', Carbon::now()->endOfMonth()))->endOfDay();

            $topProducts = SaleItem::select(
                'product_id',
                DB::raw('SUM(quantity) as total_sold'),
                DB::raw('SUM(total_price) as total_revenue')
            )
                ->with('product.category')
                ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('transaction_date', [$startDate, $endDate]);
                })
                ->groupBy('product_id')
                ->orderBy('total_sold', 'desc')
                ->limit(10)
                ->get();

            return response()->json($topProducts);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'message' => 'Akses ditolak: Anda tidak memiliki izin untuk melihat produk terlaris',
                'errors' => [
                    'permission' => ['Anda tidak memiliki izin yang diperlukan']
                ]
            ], 403);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Gagal mengambil data produk terlaris: Terjadi kesalahan database',
                'errors' => [
                    'database' => ['Terjadi kesalahan saat mengambil data dari database']
                ]
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data produk terlaris: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
