<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index()
    {
        try {
            $categories = Category::where('is_active', true)->get();
            return response()->json($categories);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil data kategori: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
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
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal membuat kategori: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function show($code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();
            return response()->json($category);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Kategori tidak ditemukan',
                'errors' => [
                    'not_found' => ['Kategori yang diminta tidak dapat ditemukan']
                ]
            ], 404);
        }
    }

    public function update(Request $request, $code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();

            $validated = $request->validate([
                'code' => 'required|unique:categories,code,'.$category->code,
                'name' => 'required',
            ]);

            $updateData = [
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description ?? $category->description,
            ];

            if ($request->user()) {
                $updateData['updated_by'] = $request->user()->user_id;
            }

            $category->update($updateData);

            return response()->json($category);
        } catch (ValidationException $e) {
            $firstError = collect($e->errors())->first()[0];
            return response()->json([
                'message' => 'Validasi gagal: ' . $firstError,
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal memperbarui kategori: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }

    public function destroy($code)
    {
        try {
            $category = Category::where('code', $code)->firstOrFail();
            $category->update(['is_active' => false]);

            return response()->json(['message' => 'Kategori berhasil dinonaktifkan']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal menonaktifkan kategori: ' . $e->getMessage(),
                'errors' => [
                    'server' => [$e->getMessage()]
                ]
            ], 500);
        }
    }
}