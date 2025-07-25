<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('category')->where('is_active', true);
        
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        
        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:products',
            'name' => 'required',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        $product = Product::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock,
            'min_stock' => $request->min_stock ?? 0,
            'unit' => $request->unit ?? 'pcs',
            'created_by' => $request->user()->id,
        ]);

        // Create stock log for initial stock
        if ($request->stock > 0) {
            StockLog::create([
                'code' => 'STK-' . Str::random(8),
                'product_id' => $product->id,
                'type' => 'in',
                'quantity' => $request->stock,
                'stock_before' => 0,
                'stock_after' => $request->stock,
                'notes' => 'Initial stock',
                'created_by' => $request->user()->id,
            ]);
        }

        return response()->json($product->load('category'), 201);
    }

    public function show($id)
    {
        $product = Product::with('category')->findOrFail($id);
        return response()->json($product);
    }

    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
        $request->validate([
            'code' => 'required|unique:products,code,' . $id,
            'name' => 'required',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
        ]);

        $product->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'min_stock' => $request->min_stock ?? 0,
            'unit' => $request->unit ?? 'pcs',
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($product->load('category'));
    }

    public function updateStock(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        
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
            'product_id' => $product->id,
            'type' => $request->type,
            'quantity' => abs($quantity),
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'notes' => $request->notes,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($product);
    }
}