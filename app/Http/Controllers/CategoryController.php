<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::where('is_active', true)->get();
        return response()->json($categories);
    }

    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|unique:categories',
            'name' => 'required',
        ]);

        $category = Category::create([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'created_by' => $request->user()->id,
        ]);

        return response()->json($category, 201);
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    public function update(Request $request, $id)
    {
        $category = Category::findOrFail($id);
        
        $request->validate([
            'code' => 'required|unique:categories,code,' . $id,
            'name' => 'required',
        ]);

        $category->update([
            'code' => $request->code,
            'name' => $request->name,
            'description' => $request->description,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json($category);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);
        $category->update(['is_active' => false]);
        
        return response()->json(['message' => 'Category deactivated successfully']);
    }
}