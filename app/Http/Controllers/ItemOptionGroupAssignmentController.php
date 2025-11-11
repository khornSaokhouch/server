<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\ItemOptionGroup;

class ItemOptionGroupAssignmentController extends Controller
{
    /**
     * List all item-option group assignments
     */
    public function index()
    {
        // Return all items with their assigned option groups
        $items = Item::with('optionGroups')->get();
        return response()->json($items);
    }

    /**
     * Assign an option group to an item
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'item_option_group_id' => 'required|exists:item_option_groups,id',
        ]);
    
        $item = Item::findOrFail($validated['item_id']);
    
        // Attach group (avoid duplicates)
        $item->optionGroups()->syncWithoutDetaching([$validated['item_option_group_id']]);
    
        // Reload item with option groups and their active options
        $item->load(['optionGroups.options' => function ($query) {
            $query->where('is_active', 1);
        }]);
      
    
        return response()->json($item, 201); // return the full item data
    }
    

    /**
 * Show assigned option groups for a specific item
 */public function show($itemId)
{
    $item = Item::with(['optionGroups.options' => function ($query) {
        $query->where('is_active', 1); // only active options
    }])->findOrFail($itemId);

    // Convert icon paths to full URLs
    $item->optionGroups->transform(function ($group) {
        $group->options->transform(function ($option) {
            if ($option->icon) {
                $option->icon = asset('storage/' . $option->icon);
            }
            return $option;
        });
        return $group;
    });

    return response()->json($item);
}



    /**
     * Remove an option group from an item
     */
    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'item_option_group_id' => 'required|exists:item_option_groups,id',
        ]);

        $item = Item::findOrFail($validated['item_id']);
        $item->optionGroups()->detach($validated['item_option_group_id']);

        return response()->json(['message' => 'Option group removed successfully']);
    }
}
