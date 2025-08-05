<?php

namespace App\Http\Controllers;

use App\Models\StockLog;
use App\Models\Product;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function logs(Request $request)
    {
        $query = StockLog::with(['product.category', 'creator'])
            ->orderBy('created_at', 'desc');

        if ($request->has('product_code')) {
            $product = Product::where('code', $request->product_code)->first();
            if ($product) {
                $query->where('product_id', $product->id);
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
    }

    public function lowStock()
    {
        $products = Product::with('category')
            ->whereRaw('stock <= min_stock')
            ->where('is_active', true)
            ->get();

        return response()->json($products);
    }
}
