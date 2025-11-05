<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ItemController extends Controller
{
    /**
     * List all items
     */
    public function index()
    {
        $items = Item::with(['shop', 'category'])
            ->orderBy('display_order', 'asc')
            ->get();

        return response()->json($items);
    }

    /**
     * Store a new item
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:shops,id',
            'category_id' => 'required|integer|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'price_cents' => 'required|integer|min:0',
            'image_url' => 'nullable|image|max:2048', // optional image upload
            'is_available' => 'sometimes|boolean',
            'display_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image_url')) {
            $validated['image_url'] = $request->file('image_url')->store('items', 'public');
        }

        $item = Item::create($validated);

        return response()->json([
            'message' => 'Item created successfully',
            'data' => $item,
        ], 201);
    }

    /**
     * Show a specific item
     */
    public function show(Item $item)
    {
        $item->load(['shop', 'category']);
        return response()->json($item);
    }

    /**
     * Update an existing item
     */
    public function update(Request $request, Item $item)
    {
        $validated = $request->validate([
            'shop_id' => 'sometimes|integer|exists:shops,id',
            'category_id' => 'sometimes|integer|exists:categories,id',
            'name' => 'sometimes|string|max:150',
            'description' => 'nullable|string',
            'price_cents' => 'sometimes|integer|min:0',
            'image_url' => 'nullable|image|max:2048',
            'is_available' => 'sometimes|boolean',
            'display_order' => 'nullable|integer',
        ]);

        if ($request->hasFile('image_url')) {
            // Delete old image if exists
            if ($item->image_url) {
                Storage::disk('public')->delete($item->image_url);
            }
            $validated['image_url'] = $request->file('image_url')->store('items', 'public');
        }

        $item->update($validated);

        return response()->json([
            'message' => 'Item updated successfully',
            'data' => $item,
        ]);
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
}
