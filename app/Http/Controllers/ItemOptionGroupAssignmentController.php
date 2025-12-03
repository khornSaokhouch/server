<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\ItemOptionGroup;
use Illuminate\Support\Str;

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
 */


public function show(Request $request, $itemId)
{
    $shopId = $request->query('shop_id');

    $item = Item::with(['optionGroups.options' => function ($q) use ($shopId) {
        $q->where('is_active', 1)
          ->when($shopId, function ($q2) use ($shopId) {
              $q2->whereHas('shopOptionStatuses', function ($q3) use ($shopId) {
                  $q3->where('shop_id', $shopId)
                     ->where('status', 1);
              });
          });
    }])->findOrFail($itemId);

    // Remove option groups that have no options left
    $item->optionGroups = $item->optionGroups->filter(function ($group) {
        return $group->options->isNotEmpty();
    })->values();

    // Convert icon paths to full URLs safely (avoid double-prefixing full URLs)
    $item->optionGroups->transform(function ($group) {
        
        return $group;
    });

    // Convert to array and remove the key so response won't include "option_groups"
    $data = $item->toArray();
    if (array_key_exists('option_groups', $data)) {
        unset($data['option_groups']);
    }

    return response()->json($data);
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

    // public function destroy($id)
    // {
    //     $group = ItemOptionGroup::findOrFail($id);
    //     $group->delete();
    //     return response()->json(['message' => 'Deleted successfully']);
    // }
}
