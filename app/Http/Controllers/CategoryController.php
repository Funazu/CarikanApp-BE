<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Support\Facades\Validator;
use App\Services\CacheHelper;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories.
     */
    public function index()
    {
        $categories = CacheHelper::remember(['categories'], 'categories_all', 3600, function () {
            return Category::all();
        });
        return response()->json($categories, 200);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:categories,name|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $category = Category::create($request->only(['name', 'description']));

        CacheHelper::flush(['categories']);

        return response()->json([
            'message' => 'Category created successfully',
            'category' => $category
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show($id)
    {
        $category = CacheHelper::remember(['categories'], "category_show_{$id}", 3600, function () use ($id) {
            return Category::find($id);
        });

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json($category, 200);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, $id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|unique:categories,name,' . $id . '|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        $category->update($request->only(['name', 'description']));

        CacheHelper::flush(['categories', 'items']);

        return response()->json([
            'message' => 'Category updated successfully',
            'category' => $category
        ], 200);
    }

    /**
     * Remove the specified category.
     */
    public function destroy($id)
    {
        $category = Category::find($id);

        if (!$category) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        if ($category->items()->exists()) {
            return response()->json([
                'message' => 'Cannot delete category as it is currently linked to reports.'
            ], 400);
        }

        $category->delete();

        CacheHelper::flush(['categories', 'items']);

        return response()->json([
            'message' => 'Category deleted successfully'
        ], 200);
    }
}
