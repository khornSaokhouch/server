<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\ItemOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemOwnerController extends Controller
{
    /**
     * List all items for a given owner or category
     */
    public function index(Request $request)
    {
        $query = ItemOwner::with(['item', 'category', 'shop'])
            ->where('inactive', 1); // only show active items
    
        if ($request->shop_id) {
            $query->where('shop_id', $request->shop_id);
        }
    
        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }
    
        $items = $query->get();
    
        return response()->json([
            'message' => 'Items retrieved successfully',
            'data' => $items
        ], 200);
    }
    

    /**
     * Store a new item owner record
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
    
        // Validate that the input is an array of objects
        $validatedData = $request->validate([
            '*.item_id' => 'required|exists:items,id',
            '*.shop_id' => 'required|exists:shops,id',
            '*.category_id' => 'required|exists:categories,id',
            '*.inactive' => 'nullable|boolean',
        ]);
    
        $createdItems = [];
    
        foreach ($validatedData as $data) {
            $data['inactive'] = $data['inactive'] ?? 1;
            $itemOwner = ItemOwner::create($data);
            // $itemOwner->load(['item', 'shop', 'category']);
            $createdItems[] = $itemOwner;
        }
    
        return response()->json([
            'message' => 'ItemOwners created successfully',
            'data' => $createdItems
        ], 201);
    }
    
    /**
     * Update inactive status
     */
    public function updateStatus(Request $request, $id)
    {
        $itemOwner = ItemOwner::find($id);

        if (!$itemOwner) {
            return response()->json([
                'error' => 'ItemOwner not found'
            ], 404);
        }

        $request->validate([
            'inactive' => 'required|boolean'
        ]);

        $itemOwner->inactive = $request->inactive;
        $itemOwner->save();

        return response()->json([
            'message' => 'Status updated successfully',
            'data' => $itemOwner
        ], 200);
    }

    /**
     * Delete a record
     */
    public function destroy($id)
    {
        $itemOwner = ItemOwner::find($id);

        if (!$itemOwner) {
            return response()->json([
                'error' => 'ItemOwner not found'
            ], 404);
        }

        $itemOwner->delete();

        return response()->json([
            'message' => 'ItemOwner deleted successfully'
        ], 200);
    }


//     public function categoriesByOwner($owner_id)
// {
//     // Get categories for items that belong to this owner and are active
//     $categories = ItemOwner::with('category')
//         ->where('owner_id', $owner_id)
//         ->where('inactive', 1) // only active items
//         ->get()
//         ->pluck('category')    // get the category relation
//         ->unique('id')         // remove duplicates
//         ->values();            // reset array keys

//     return response()->json([
//         'message' => 'Categories retrieved successfully',
//         'data' => $categories
//     ], 200);
// }

public function categoriesByOwner($shop_id)
{
    // Get categories for items that belong to this owner and are active,
    // and only categories with status != 0
    $categories = Category::whereHas('itemOwners', function ($query) use ($shop_id) {
            $query->where('shop_id', $shop_id)
                  ->where('inactive', 1); // only active items
        })
        ->where('status', '!=', 0) // exclude categories with status = 0
        ->get();

    return response()->json([
        'message' => 'Categories retrieved successfully',
        'data' => $categories
    ], 200);
}

public function itemsByOwnerAndCategory($shop_id)
{
    $items = ItemOwner::with(['item', 'category', 'shop']) // eager load relationships
        ->where('shop_id', $shop_id)
        ->where('inactive', 1) // only active items
        ->whereHas('category', function ($query) {
            $query->where('status', 1); // only categories with status = 1
        })
        ->whereHas('item', function ($query) {
            $query->where('is_available', 1); // only items that are available
        })
        ->get()
        ->map(function ($itemOwner) {
            return [
                'shop_id' => $itemOwner->shop_id,
                'category_id' => $itemOwner->category_id,
                'category' => [
                    'id' => $itemOwner->category->id ?? null,
                    'name' => $itemOwner->category->name ?? null,
                    'image_url' => $itemOwner->category->image_category_url ?? null,
                    'description' => $itemOwner->category->description ?? null,
                    'status' => $itemOwner->category->status ?? null,
                ],
                'item' => [
                    'id' => $itemOwner->item->id ?? null,
                    'name' => $itemOwner->item->name ?? null,
                    'description' => $itemOwner->item->description ?? null,
                    'price_cents' => $itemOwner->item->price_cents ?? null,
                    'image_url' => $itemOwner->item->image_url ?? null,
                    'is_available' => $itemOwner->item->is_available ?? null,
                ],
            ];
        });
    
    return response()->json([
        'message' => 'Items retrieved successfully',
        'data' => $items
    ], 200);
}


}
