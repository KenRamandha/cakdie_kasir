<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'code' => 'required|unique:categories',
                'name' => 'required',
            ]);

            $categoryData = [
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description ?? null,
                'is_active' => true,
            ];

            if ($request->user()) {
                $categoryData['created_by'] = $request->user()->user_id;
                $categoryData['updated_by'] = $request->user()->user_id;
            } else {
                Log::warning('Category created without authenticated user');
            }

            $category = Category::create($categoryData);

            return response()->json($category, 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Category creation error: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create category',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();
            return response()->json($category);
        } catch (\Exception $e) {
            Log::error('Category not found: ' . $e->getMessage());
            return response()->json(['message' => 'Category not found'], 404);
        }
    }

    public function update(Request $request, $code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();

            $updateData = [
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description ?? null,
            ];

            if ($request->user()) {
                $updateData['updated_by'] = $request->user()->user_id;
            }

            $category->update($updateData);

            return response()->json($category);
        } catch (\Exception $e) {
            Log::error('Category update error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update category'], 500);
        }
    }

    public function destroy($code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();
            $category->update(['is_active' => false]);

            return response()->json(['message' => 'Category deactivated successfully']);
        } catch (\Exception $e) {
            Log::error('Category deactivation error: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to deactivate category'], 500);
        }
    }
}
