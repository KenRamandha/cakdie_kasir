<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\ExportService;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category:id,name,code']);

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter low stock products
        if ($request->boolean('low_stock')) {
            $query->lowStock();
        }

        // Search by name or code
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('name')
                         ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product = Product::create([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'stock' => $request->stock,
            'min_stock' => $request->min_stock,
            'unit' => $request->unit,
            'is_active' => $request->get('is_active', true),
        ]);

        // Create initial stock log
        if ($request->stock > 0) {
            $product->updateStock($request->stock, 'in', 'Initial stock');
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully',
            'data' => $product->load('category:id,name,code')
        ], 201);
    }

    public function show(Product $product)
    {
        return response()->json([
            'success' => true,
            'data' => $product->load([
                'category:id,name,code',
                'createdBy:id,name',
                'updatedBy:id,name',
                'stockLogs' => function($query) {
                    $query->with('createdBy:id,name')
                          ->orderBy('created_at', 'desc')
                          ->limit(10);
                }
            ])
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'min_stock' => 'required|integer|min:0',
            'unit' => 'required|string|max:50',
            'is_active' => 'boolean',
        ]);

        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'category_id' => $request->category_id,
            'price' => $request->price,
            'cost_price' => $request->cost_price,
            'min_stock' => $request->min_stock,
            'unit' => $request->unit,
            'is_active' => $request->get('is_active', $product->is_active),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully',
            'data' => $product->load('category:id,name,code')
        ]);
    }

    public function destroy(Product $product)
    {
        // Check if product has sales
        if ($product->saleItems()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete product that has sales history'
            ], 422);
        }

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    public function updateStock(Request $request, Product $product)
    {
        $request->validate([
            'type' => 'required|in:in,out,adjustment',
            'quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
        ]);

        if ($request->type === 'out' && $product->stock < $request->quantity) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock'
            ], 422);
        }

        $product->updateStock(
            $request->quantity,
            $request->type,
            $request->notes
        );

        return response()->json([
            'success' => true,
            'message' => 'Stock updated successfully',
            'data' => $product->fresh()
        ]);
    }

    public function getLowStockProducts()
    {
        $products = Product::with('category:id,name,code')
                          ->lowStock()
                          ->where('is_active', true)
                          ->orderBy('stock')
                          ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function getActiveProducts(Request $request)
    {
        $query = Product::active()->with('category:id,name,code');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->select('id', 'code', 'name', 'price', 'stock', 'category_id')
                         ->orderBy('name')
                         ->get();

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function toggle(Product $product)
    {
        $product->update([
            'is_active' => !$product->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product status updated successfully',
            'data' => $product
        ]);
    }

    public function exportProducts(Request $request)
    {
        // Only allow pemilik to export
        if (!$request->user()->canViewRevenue()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $format = $request->get('format', 'array'); // array, csv

        try {
            $exportService = app(\App\Services\ExportService::class);
            
            if ($format === 'csv') {
                $data = $exportService->exportProductsToArray();
                
                $filename = 'products_export_' . date('Y-m-d_H-i-s') . '.csv';
                $handle = fopen('php://temp', 'w');
                
                foreach ($data as $row) {
                    fputcsv($handle, $row);
                }
                
                rewind($handle);
                $csv = stream_get_contents($handle);
                fclose($handle);
                
                return response($csv)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            } else {
                $data = $exportService->exportProductsToArray();
                
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