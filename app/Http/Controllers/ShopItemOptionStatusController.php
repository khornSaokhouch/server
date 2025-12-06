<?php

namespace App\Http\Controllers;

use App\Models\ShopItemOptionStatus;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;



class ShopItemOptionStatusController extends Controller
{
    // List all statuses
    public function index()
    {
        $statuses = ShopItemOptionStatus::with(['shop', 'item', 'group', 'option'])->get();        ;
        return response()->json($statuses);
    }

    // Show one status
  //  public function show($id)
    // {
    //     $status = ShopItemOptionStatus::with([
    //         'shop',
    //         'item',
    //         'optionGroup',
    //         'option' => function ($query) {
    //             $query->where('is_active', 1); // Only include active options
    //         }
    //     ])
    //     ->where('status', 1) // Only active shop_item_option_status
    //     ->find($id);
    
    //     // If the status does not exist or its option is inactive, return 404
    //     if (!$status || !$status->option) {
    //         return response()->json(['message' => 'Not found or inactive'], 404);
    //     }
    
    //     return response()->json($status);
    // }
//     public function showByItem($itemId)
// {
//     $statuses = ShopItemOptionStatus::with([
//         'item',
//         'optionGroup',
//         'option' => function ($query) {
//             $query->where('is_active', 1); // Only include active options
//         }
//     ])
//     ->where('item_id', $itemId) // Filter by item ID
//     ->where('status', 1)        // Only active statuses
//     ->get();                    // Get all matching records

//     if ($statuses->isEmpty()) {
//         return response()->json(['message' => 'No active statuses found for this item'], 404);
//     }

//     return response()->json($statuses);
// }
// public function showByItem(Request $request, $itemId,$shopId)
// {
//     try {
//         $shopId = $request->query('shop_id');

//         $statuses = ShopItemOptionStatus::with([
//             'item',
//             'optionGroup',
//             'option' => function ($query) {
//                 $query->where('is_active', 1);
//             }
//         ])
//         ->where('item_id', $itemId)
//         ->where('status', 1)
//         ->whereHas('item', fn($q) => $q->where('is_available', 1));

//         if ($shopId) {
//             $statuses->where('shop_id', $shopId);
//         }

//         $statuses = $statuses->get();

//         if ($statuses->isEmpty()) {
//             return response()->json(['message' => 'No active statuses found'], 404);
//         }

//         return response()->json($statuses);
//     } catch (\Exception $e) {
//         \Log::error('ShopItemOptionStatus error: '.$e->getMessage());
//         return response()->json([
//             'error' => $e->getMessage()
//         ], 500);
//     }
// }
public function showByItem(Request $request, $itemId, $shopId)
{
    try {
        // Build the query
        $statuses = ShopItemOptionStatus::with([
            'item',
            'optionGroup',
            'option' => function ($query) {
                $query->where('is_active', 1); // Only active options
            }
        ])
        ->where('item_id', $itemId)
        ->where('status', 1) // Only active statuses
        ->whereHas('item', fn($q) => $q->where('is_available', 1)); // Item must be available

        // Apply shop filter if provided
        if ($shopId) {
            $statuses->where('shop_id', $shopId);
        }

        // Execute query
        $statuses = $statuses->get();
        // Remove entries where option is null
        $statuses = $statuses->filter(fn($status) => $status->option !== null);

        // Check if empty
        if ($statuses->isEmpty()) {
            return response()->json(['message' => 'No active statuses found'], 404);
        }

        // Return results
        return response()->json($statuses);

    } catch (\Exception $e) {
        \Log::error('ShopItemOptionStatus error: '.$e->getMessage());
        return response()->json([
            'error' => 'Server error: ' . $e->getMessage()
        ], 500);
    }
}
// public function showByItemShop(Request $request, $itemId, $shopId)
// {
//     try {
//         $statuses = ShopItemOptionStatus::with([
//             'item',
//             'optionGroup',
//             'option' => function ($query) {
//                 $query->where('is_active', 1);
//             }
//         ])
//         ->where('item_id', $itemId)
//         ->whereHas('item', fn($q) => $q->where('is_available', 1));

//         if ($shopId) {
//             $statuses->where('shop_id', $shopId);
//         }

//         $statuses = $statuses->get();

//         // Remove entries where option is null
//         $statuses = $statuses->filter(fn($status) => $status->option !== null);

//         if ($statuses->isEmpty()) {
//             return response()->json(['message' => 'No active statuses found'], 404);
//         }

//         return response()->json($statuses->values()); // reset array keys

//     } catch (\Exception $e) {
//         \Log::error('ShopItemOptionStatus error: '.$e->getMessage());
//         return response()->json([
//             'error' => 'Server error: ' . $e->getMessage()
//         ], 500);
//     }
// }


    
    // Create a new status
   
    




    public function showByItemShop(Request $request, $itemId, $shopId)
    {
        try {
            $statusesQuery = ShopItemOptionStatus::with([
                'item',
                'optionGroup',
                'option' => function ($query) {
                    $query->where('is_active', 1);
                }
            ])
            ->where('item_id', $itemId)
            ->whereHas('item', fn($q) => $q->where('is_available', 1));
    
            if ($shopId) {
                $statusesQuery->where('shop_id', $shopId);
            }
    
            $statuses = $statusesQuery->get();
    
            // Remove entries where option is null
            $statuses = $statuses->filter(fn($status) => $status->option !== null);
    
            // Map and apply pivot existence rules:
            // - if pivot exists => keep as-is (include item_option_group_id)
            // - if pivot missing AND option_group_id == 1 => exclude the status entirely
            // - if pivot missing AND option_group_id != 1 => include but remove item_option_group_id key
            $processed = $statuses->map(function ($status) use ($itemId) {
                $arr = $status->toArray();
    
                // If no item_option_group_id present, just return as-is
                if (! array_key_exists('item_option_group_id', $arr) || $arr['item_option_group_id'] === null) {
                    return $arr;
                }
    
                $groupId = $arr['item_option_group_id'];
    
                $exists = DB::table('item_item_option_group')
                    ->where('item_id', $itemId)
                    ->where('item_option_group_id', $groupId)
                    ->exists();
    
                if ($exists) {
                    return $arr; // keep as-is
                }
    
                // pivot missing
                if ((int) $groupId) {
                    // signal to caller to remove this item by returning null
                    return null;
                }
    
                // For other groups: remove the key but keep rest
                unset($arr['item_option_group_id']);
                return $arr;
            })
            // remove nulls (those were the option_group_id == 1 + pivot-missing)
            ->filter(fn($x) => $x !== null)
            ->values();
    
            if ($processed->isEmpty()) {
                return response()->json(['message' => 'No active statuses found'], 404);
            }
    
            return response()->json($processed);
    
        } catch (\Exception $e) {
            \Log::error('ShopItemOptionStatus error: '.$e->getMessage());
            return response()->json([
                'error' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
    

    public function store(Request $request)
    {
        $data = $request->validate([
            'shop_id' => 'required|exists:shops,id',
            'item_id' => 'required|exists:items,id',
            'item_option_group_id' => 'required|exists:item_option_groups,id',
            'item_option_id' => 'required|exists:item_options,id',
            'status' => 'nullable|boolean',
        ]);
    
        try {
            $status = ShopItemOptionStatus::updateOrCreate(
                [
                    'shop_id' => $data['shop_id'],
                    'item_id' => $data['item_id'],
                    'item_option_group_id' => $data['item_option_group_id'],
                    'item_option_id' => $data['item_option_id'],
                ],
                [
                    'status' => $data['status'] ?? 1,
                ]
            );
    
            // ALWAYS return a JSON object (Eloquent model will be serialized)
            return response()->json($status, 201);
        } catch (QueryException $e) {
            if ($e->getCode() == 23000) {
                return response()->json([
                    'message' => 'Duplicate entry',
                    'error' => $e->getMessage(),
                ], 400);
            }
    
            return response()->json([
                'message' => 'Database error',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    
    // Update status
    public function update(Request $request, $id)
    {
        $status = ShopItemOptionStatus::find($id);
        if (!$status) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $data = $request->validate([
            'status' => 'required|boolean',
        ]);

        $status->update($data);
        return response()->json($status);
    }

    // Delete status
    public function destroy($id)
    {
        $status = ShopItemOptionStatus::find($id);
        if (!$status) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $status->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
