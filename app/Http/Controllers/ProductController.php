<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Category;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);

        if ($request->has('category_code')) {
            $category = Category::where('code', $request->category_code)->first();
            if ($category) {
                $query->where('category_id', $category->id);
            }
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:products',
            'name' => 'required',
            'category_code' => 'required|exists:categories,code',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $category = Category::where('code', $request->category_code)->firstOrFail();

        $product = Product::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $category->code,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock,
            'min_stock' => $request->min_stock ?? 0,
            'unit' => $request->unit ?? 'pcs',
            'created_by' => $request->user()->user_id,
        ]);

        // Create stock log for initial stock
        if ($request->stock > 0) {
            StockLog::create([
                'code' => 'STK-' . Str::random(8),
                'product_id' => $product->code,
                'type' => 'in',
                'quantity' => $request->stock,
                'stock_before' => 0,
                'stock_after' => $request->stock,
                'notes' => 'Initial stock',
                'created_by' => $request->user()->user_id,
            ]);
        }

        return response()->json($product->load('category'), 201);
    }

    public function show($code)
    {
        $product = Product::with('category')->where('code', $code)->firstOrFail();
        return response()->json($product);
    }

    public function update(Request $request, $code)
    {
        $product = Product::with('category')->where('code', $code)->firstOrFail();

        $request->validate([
            'code' => 'required|unique:products,code,' . $product->id,
            'name' => 'required|string|max:255',
            'category_code' => 'required|exists:categories,code',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string|max:20',
            'description' => 'nullable|string',
        ]);

        $category = Category::where('code', $request->category_code)->firstOrFail();

        $product->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $category->code,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock ?? 0,
            'min_stock' => $request->min_stock ?? 0,
            'unit' => $request->unit ?? 'pcs',
            'updated_by' => $request->user()->user_id,
        ]);

        return response()->json($product->load('category'));
    }

    public function updateStock(Request $request, $code)
    {
        $product = Product::where('code', $code)->firstOrFail();

        $request->validate([
            'quantity' => 'required|integer',
            'type' => 'required|in:in,out,adjustment',
            'notes' => 'nullable|string',
        ]);

        $stockBefore = $product->stock;
        $quantity = $request->quantity;

        switch ($request->type) {
            case 'in':
                $stockAfter = $stockBefore + $quantity;
                break;
            case 'out':
                $stockAfter = $stockBefore - $quantity;
                break;
            case 'adjustment':
                $stockAfter = $quantity;
                $quantity = $quantity - $stockBefore;
                break;
        }

        $product->update(['stock' => $stockAfter]);

        StockLog::create([
            'code' => 'STK-' . Str::random(8),
            'product_id' => $product->code,
            'type' => $request->type,
            'quantity' => abs($quantity),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'notes' => $request->notes,
            'created_by' => $request->user()->user_id,
        ]);

        return response()->json($product);
    }
}
