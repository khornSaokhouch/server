<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index()
    {
        $categories = Category::orderBy('display_order', 'asc')->get();
        return response()->json($categories);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'name' => 'required|string|max:100',
            'display_order' => 'nullable|integer',
            'image_category' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // new
        ]);

        // Handle image upload
        if ($request->hasFile('image_category')) {
            $path = $request->file('image_category')->store('categories', 'public');
            $validated['image_category'] = $path;
        }

        $category = Category::create($validated);

        return response()->json([
            'message' => 'Category created successfully',
            'data' => $category
        ], 201);
    }

    /**
     * Display the specified category.
     */
    public function show(Category $category)
    {
        return response()->json($category);
    }

    /**
     * Update the specified category in storage.
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'shop_id' => 'sometimes|integer|exists:shops,id',
            'name' => 'sometimes|string|max:100',
            'display_order' => 'nullable|integer',
            'image_category' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // new
        ]);

        // Handle image upload
        if ($request->hasFile('image_category')) {
            // Delete old image if exists
            if ($category->image_category && Storage::disk('public')->exists($category->image_category)) {
                Storage::disk('public')->delete($category->image_category);
            }

            $path = $request->file('image_category')->store('categories', 'public');
            $validated['image_category'] = $path;
        }

        $category->update($validated);

        return response()->json([
            'message' => 'Category updated successfully',
            'data' => $category
        ]);
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category)
    {
        // Delete associated image if exists
        if ($category->image_category && Storage::disk('public')->exists($category->image_category)) {
            Storage::disk('public')->delete($category->image_category);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category deleted successfully'
        ]);
    }
}
