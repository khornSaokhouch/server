<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index()
    {
        $user = Auth::user();

        if ($user && $user->role === 'admin') {
            // Admin can see all categories
            $categories = Category::orderBy('id', 'desc')->get();
        } else {
            // Non-admin see only active categories
            $categories = Category::where('status', 1)
                ->orderBy('id', 'desc')
                ->get();
        }

        // Optional: add full URL for image_category
        $categories->transform(function ($category) {
            $category->image_category_url = $category->image_category 
                ? asset('storage/' . $category->image_category) 
                : null;
            return $category;
        });

        return response()->json([
            'message' => 'Categories retrieved successfully',
            'data' => $categories
        ], 200);
    }


    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
    
        // ✅ Only allow admin users
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden: only admin can create categories'
            ], 403);
        }
    
        try {
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:categories,name',
                'status' => 'sometimes|in:0,1',
                'image_category' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:1024',
            ]);
    
            // Handle image upload
            if ($request->hasFile('image_category')) {
                $validated['image_category'] = $this->uploadImage(
                    $request->file('image_category'),
                    'categories/icons'
                );
            }

            // Default status to 1 if not provided
            $validated['status'] = $validated['status'] ?? 1;
    
            // Create category
            $category = Category::create($validated);
    
            return response()->json([
                'message' => 'Category created successfully',
                'data' => $category
            ], 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Return validation errors
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            // Return general errors
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
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
        $user = Auth::user();
    
        // ✅ Only allow admin users
        if (!$user || $user->role !== 'admin') {
            return response()->json([
                'message' => 'Forbidden: only admin can update categories'
            ], 403);
        }
    
        try {
            // Validate inputs
            $validated = $request->validate([
                'name' => 'sometimes|string|max:100|unique:categories,name,' . $category->id,
                'status' => 'sometimes|in:0,1',
                'image_category' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:1024',
            ]);
    
            // Handle image upload
            if ($request->hasFile('image_category')) {
                // Delete old image if exists
                if ($category->image_category && Storage::disk('public')->exists($category->image_category)) {
                    Storage::disk('public')->delete($category->image_category);
                }
    
                // Store new image
                $validated['image_category'] = $this->uploadImage(
                    $request->file('image_category'),
                    'categories/icons'
                );
            }
    
            // Update category
            $category->update($validated);
    
            return response()->json([
                'message' => 'Category updated successfully',
                'data' => $category
            ], 200);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage()
            ], 500);
        }
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

    private function uploadImage($file, $directory)
    {
        $fileName = 'category_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($directory, $fileName, 'public');
    }
}
