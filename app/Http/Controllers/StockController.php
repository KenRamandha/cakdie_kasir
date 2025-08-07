<?php

namespace App\Http\Controllers;

use App\Models\StockLog;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;

class StockController extends Controller
{
    public function logs(Request $request)
    {
        try {
            $query = StockLog::with(['product.category', 'creator'])
                ->orderBy('created_at', 'desc');

            if ($request->has('product_code')) {
                $product = Product::where('code', $request->product_code)->first();
                if ($product) {
                    $query->where('product_id', $product->code);
                }
            }

            if ($request->has('type') && in_array($request->type, ['in', 'out', 'adjustment'])) {
                $query->where('type', $request->type);
            }

            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = '%' . $request->search . '%';

                $query->where(function ($q) use ($searchTerm) {
                    $q->whereHas('product', function ($productQuery) use ($searchTerm) {
                        $productQuery->where('code', 'like', $searchTerm)
                            ->orWhere('name', 'like', $searchTerm);
                    })
                        ->orWhereHas('creator', function ($userQuery) use ($searchTerm) {
                            $userQuery->where('name', 'like', $searchTerm);
                        })
                        ->orWhere('reference', 'like', $searchTerm)
                        ->orWhere('note', 'like', $searchTerm);
                });
            }

            return response()->json(
                $query->paginate(20)
            );
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Gagal mengambil log stok: Terjadi kesalahan database',
                'errors' => [
                    'database' => [$e->getMessage()]
                ]
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil log stok: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function lowStock()
    {
        try {
            $products = Product::with('category')
                ->whereRaw('stock <= min_stock')
                ->where('is_active', true)
                ->get();

            return response()->json($products);
        } catch (QueryException $e) {
            return response()->json([
                'message' => 'Gagal mengambil produk stok rendah: Terjadi kesalahan database',
                'errors' => [
                    'database' => [$e->getMessage()]
                ]
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil produk stok rendah: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
