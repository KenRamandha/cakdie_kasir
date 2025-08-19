<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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

            $products = $query->get()->map(function ($product) {
                $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;
                return $product;
            });

            return response()->json($products);
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
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|unique:products,code',
                'name' => 'required|string|max:255',
                'category_code' => 'required|string|exists:categories,code',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'unit' => 'nullable|string|max:20',
                'description' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = Category::where('code', $request->category_code)->firstOrFail();

            $productData = [
                'code' => trim($request->code),
                'name' => trim($request->name),
                'description' => $request->description ? trim($request->description) : null,
                'category_id' => $category->code,
                'price' => (float) $request->price,
                'cost_price' => $request->cost_price ? (float) $request->cost_price : null,
                'stock' => (int) $request->stock,
                'min_stock' => $request->min_stock ? (int) $request->min_stock : 0,
                'unit' => $request->unit ? trim($request->unit) : 'pcs',
                'created_by' => $request->user()->user_id,
                'updated_by' => $request->user()->user_id,
                'image_path' => null,
            ];

            if ($request->hasFile('image')) {
                $image = $request->file('image');

                $directory = 'public/products';
                if (!Storage::exists($directory)) {
                    Storage::makeDirectory($directory);
                }

                $filename = 'product-' . Str::uuid() . '.' . $image->getClientOriginalExtension();

                try {
                    $stored = $image->storeAs('products', $filename, 'public');

                    if ($stored) {
                        $productData['image_path'] = $stored;
                    } else {
                        return response()->json([
                            'message' => 'Failed to store image file',
                            'errors' => ['image' => ['Could not save image to storage']]
                        ], 500);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Failed to upload image',
                        'errors' => ['image' => ['Image upload failed: ' . $e->getMessage()]]
                    ], 500);
                }
            }

            $product = Product::create($productData);

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

            $product->load('category');
            $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;

            return response()->json($product, 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to create product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $product = Product::with('category')->where('code', $code)->firstOrFail();
            $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;
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

            $validator = Validator::make($request->all(), [
                'code' => 'required|string|unique:products,code,' . $product->code . ',code',
                'name' => 'required|string|max:255',
                'category_code' => 'required|string|exists:categories,code',
                'price' => 'required|numeric|min:0',
                'cost_price' => 'nullable|numeric|min:0',
                'stock' => 'required|integer|min:0',
                'min_stock' => 'nullable|integer|min:0',
                'unit' => 'nullable|string|max:20',
                'description' => 'nullable|string|max:1000',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
                'remove_image' => 'nullable|string|in:true,false',
                '_method' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $category = Category::where('code', $request->category_code)->firstOrFail();

            $updateData = [
                'code' => trim($request->code),
                'name' => trim($request->name),
                'description' => $request->description ? trim($request->description) : null,
                'category_id' => $category->code,
                'price' => (float) $request->price,
                'cost_price' => $request->cost_price ? (float) $request->cost_price : null,
                'stock' => (int) $request->stock,
                'min_stock' => $request->min_stock ? (int) $request->min_stock : $product->min_stock,
                'unit' => $request->unit ? trim($request->unit) : $product->unit,
                'updated_by' => $request->user()->user_id,
            ];

            $shouldRemoveImage = $request->input('remove_image') === 'true';
            $hasImageUpload = $request->hasFile('image') && $request->file('image')->isValid();

            $updateData['image_path'] = $product->image_path;

            if ($hasImageUpload) {
                $imageFile = $request->file('image');

                if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                    $deleted = Storage::disk('public')->delete($product->image_path);
                }

                $directory = 'products';
                if (!Storage::disk('public')->exists($directory)) {
                    Storage::disk('public')->makeDirectory($directory);
                }

                $filename = 'product-' . Str::uuid() . '.' . $imageFile->getClientOriginalExtension();

                try {
                    $path = $imageFile->storeAs('products', $filename, 'public');

                    if ($path) {
                        $updateData['image_path'] = $path;
                    } else {
                        return response()->json([
                            'message' => 'Failed to store image file',
                            'errors' => ['image' => ['Could not save image to storage']]
                        ], 500);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'message' => 'Failed to upload image',
                        'errors' => ['image' => ['Image upload failed: ' . $e->getMessage()]]
                    ], 500);
                }
            } elseif ($shouldRemoveImage) {
                $updateData['image_path'] = null;
            }

            $updated = $product->update($updateData);

            $product->refresh();

            $product = $product->fresh(['category']);
            $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;

            return response()->json($product);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update product',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateStock(Request $request, $code)
    {
        try {
            $product = Product::where('code', $code)->firstOrFail();

            $validator = Validator::make($request->all(), [
                'quantity' => 'required|integer',
                'type' => 'required|in:in,out,adjustment',
                'notes' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

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

            $product->image_url = $product->image_path ? Storage::url($product->image_path) : null;

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
