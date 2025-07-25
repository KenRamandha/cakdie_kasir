<?php
namespace App\Http\Controllers;

use App\Models\StockLog;
use App\Models\Product;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function logs(Request $request)
    {
        $query = StockLog::with(['product.category', 'creator']);
        
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }
        
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }
        
        return response()->json(
            $query->orderBy('created_at', 'desc')->paginate(20)
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