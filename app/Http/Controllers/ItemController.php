<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Str;

class ItemController extends Controller
{
    /**
     * List all items
     */
    public function index()
    {
        // Fetch items that are available and belong to active categories
        $items = Item::with('category')
            ->whereHas('category', function ($query) {
                $query->where('status', 1);
            })
            ->get();

        // Group items by category
        $grouped = $items->groupBy(function($item) {
            return $item->category->id;
        })->map(function($items, $categoryId) {
            $category = $items->first()->category; // get category from first item
            return [
                'category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'image_url' => $category->image_category_url ?? null,
                    'description' => $category->description,
                    'status' => $category->status,
                ],
                'items' => $items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'price' => $item->price_cents,
                        'description' => $item->description,
                        'is_available' => $item->is_available,
                        'image_url' => $item->image_url,
                        // add more fields if needed
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'message' => 'Items retrieved successfully',
            'data' => $grouped
        ], 200);
    }

    
    /**
     * Store a new item
     */
    public function store(Request $request)
{
    // 1️⃣ Simulate admin check (change role to test)
    $user = $request->user(); // Authenticated user
    if (!$user || $user->role !== 'admin') {
        return response()->json([
            'error' => 'Unauthorized. Only admins can create items.'
        ], 403); // 403 Forbidden
    }

    // 2️⃣ Simulate validation errors
    $validated = $request->validate([
        'category_id' => 'required|integer|exists:categories,id',
        'name' => 'required|string|max:150',
        'description' => 'nullable|string',
        'price_cents' => 'required|numeric|min:0',
        'image_url' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:2048',
        'is_available' => 'required|in:0,1',
    ]);

    // 3️⃣ Simulate category status check
    $category = Category::find($validated['category_id']);
    if (!$category || $category->status !== 1) {
        return response()->json([
            'error' => 'Cannot add item to an inactive or non-existing category.'
        ], 400); // 400 Bad Request
    }

    // 4️⃣ Handle image upload if exists
    if ($request->hasFile('image_url')) {
        $validated['image_url'] = $this->uploadImage($request->file('image_url'), 'items');
    }

    // 5️⃣ Create the item
    $item = Item::create($validated);

    return response()->json([
        'message' => 'Item created successfully',
        'data' => $item,
    ], 201);
}

    /**
     * Show a specific item
     */
    public function show($id)
    {
        // Find item with relations
        $item = Item::with(['category'])->find($id);
    
        // 404 if item not found
        if (!$item) {
            return response()->json([
                'error' => 'Item not found.'
            ], 404);
        }
    
        // 400 if category is inactive
        if (!$item->category || $item->category->status !== 1) {
            return response()->json([
                'error' => 'Item belongs to an inactive or invalid category.'
            ], 400);
        }
    
        // 200 OK
        return response()->json([
            'message' => 'Item retrieved successfully',
            'data' => $item
        ], 200);
    }
    
    public function showAllByCategory($categoryId)
    {
        // 1️⃣ Find the category
        $category = Category::find($categoryId);
    
        // 404 if category not found
        if (!$category) {
            return response()->json([
                'error' => 'Category not found.'
            ], 404);
        }
    
        // 400 if category is inactive
        if ($category->status !== 1) {
            return response()->json([
                'error' => 'Category is inactive.'
            ], 400);
        }
    
        // 2️⃣ Get all items in this category that are available
        $items = Item::with(['shop'])
            ->where('category_id', $categoryId)
            ->where('is_available', 1)
            ->get();
    
        // 404 if no items found
        if ($items->isEmpty()) {
            return response()->json([
                'error' => 'No items found in this category.'
            ], 404);
        }
    
        // 3️⃣ Return success
        return response()->json([
            'message' => 'Items retrieved successfully',
            'data' => $items
        ], 200);
    }
    


    /**
     * Update an existing item
     */ public function update(Request $request, $id)
    {
        $item = Item::find($id);

        if (!$item) {
            return response()->json([
                'error' => 'Item not found.'
            ], 404);
        }

        $validated = $request->validate([
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'price_cents' => 'sometimes|integer|min:0',
            'image_url' => 'nullable|image|max:2048',
            'is_available' => 'sometimes|in:0,1',
        ]);

        // Check category status if category_id is provided
        if (isset($validated['category_id'])) {
            $category = Category::find($validated['category_id']);
            if (!$category || $category->status !== 1) {
                return response()->json([
                    'error' => 'Cannot assign item to an inactive category.'
                ], 400);
            }
        }

        // Handle image upload
        if ($request->hasFile('image_url')) {
            if ($item->image_url) {
                Storage::disk('public')->delete($item->image_url);
            }
            $validated['image_url'] = $this->uploadImage($request->file('image_url'), 'items');
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ], 200);
    }

    /**
     * Delete an item
     */
    public function destroy(Item $item)
    {
        if ($item->image_url) {
            Storage::disk('public')->delete($item->image_url);
        }

        $item->delete();

        return response()->json([
            'message' => 'Item deleted successfully',
        ]);
    }

    private function uploadImage($file, $directory)
{
    $fileName = 'item_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
    return $file->storeAs($directory, $fileName, 'public');
}

}
