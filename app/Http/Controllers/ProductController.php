<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Product::with('category')->where('is_active', true);

            if ($request->has('category_code')) {
                $category = Category::where('code', $request->category_code)->first();
                if ($category) {
                    $query->where('category_id', $category->code);
                }
            }

            return response()->json($query->get());
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data produk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
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
                'updated_by' => $request->user()->user_id,
            ]);

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
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat produk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $product = Product::with('category')->where('code', $code)->firstOrFail();
            return response()->json($product);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Produk yang diminta tidak dapat ditemukan',
                'errors' => [
                    'not_found' => ['Produk yang diminta tidak dapat ditemukan']
                ]
            ], 404);
        }
    }

    public function update(Request $request, $code)
    {
        try {
            $product = Product::with('category')->where('code', $code)->firstOrFail();

            $request->validate([
                'code' => 'required|unique:products,code,' . $product->code,
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
                'stock' => $request->stock ?? $product->stock,
                'min_stock' => $request->min_stock ?? $product->min_stock,
                'unit' => $request->unit ?? $product->unit,
                'updated_by' => $request->user()->user_id,
            ]);

            return response()->json($product->load('category'));
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui produk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function updateStock(Request $request, $code)
    {
        try {
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
                    if ($quantity > $stockBefore) {
                        return response()->json([
                            'message' => 'Stok yang tersedia tidak mencukupi',
                            'errors' => [
                                'quantity' => ['Stok yang tersedia tidak mencukupi']
                            ]
                        ], 422);
                    }
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
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui stok: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function destroy($code)
    {
        try {
            $product = Product::where('code', $code)->firstOrFail();
            $product->update(['is_active' => false]);

            return response()->json(['message' => 'Produk berhasil dinonaktifkan']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menonaktifkan produk: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}
