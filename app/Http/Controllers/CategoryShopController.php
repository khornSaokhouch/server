<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Shop;
use App\Models\Category;

class CategoryShopController extends Controller
{
    /**
     * Display all categories for a shop with their status
     */
    public function index($shopId)
    {
        $shop = Shop::with(['categories'])->find($shopId);

        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }

        return response()->json($shop->categories);
    }

    /**
     * Attach a category to a shop
     */
    public function attachCategory(Request $request, $shopId)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'status' => 'boolean'
        ]);
    
        $shop = Shop::find($shopId);
        if (!$shop) {
            return response()->json(['message' => 'Shop not found'], 404);
        }
    
        $status = $request->status ?? 1;
        
        // Attach category if not already attached
        $shop->categories()->syncWithoutDetaching([
            $request->category_id => ['status' => $status]
        ]);
    
        // Load the attached category
        $attachedCategory = $shop->categories()
            ->where('categories.id', $request->category_id)
            ->first();
    
        return response()->json([
            'message' => 'Category attached successfully',
            'category' => $attachedCategory
        ]);
    }
    

    /**
     * Update category status for a shop
     */
    public function updateStatus(Request $request, $categoryId, $shopId)
    {
        $request->validate([
            'status' => 'required|boolean'
        ]);
    
        $shop = Shop::find($shopId);
    
        if (!$shop || !$shop->categories()->where('category_id', $categoryId)->exists()) {
            return response()->json(['message' => 'Category or Shop not found'], 404);
        }
    
        $shop->categories()->updateExistingPivot($categoryId, [
            'status' => $request->status
        ]);
    
        return response()->json(['message' => 'Category status updated successfully']);
    }
    
    /**
     * Detach a category from a shop
     */
    public function detachCategory($shopId, $categoryId)
    {
        $shop = Shop::find($shopId);
        if (!$shop || !$shop->categories()->where('category_id', $categoryId)->exists()) {
            return response()->json(['message' => 'Category or Shop not found'], 404);
        }

        $shop->categories()->detach($categoryId);

        return response()->json(['message' => 'Category detached successfully']);
    }
}
