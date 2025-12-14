<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * Display a listing of promotions.
     */
  // in PromotionController.php

// public function index(Request $request)
// {
//     // if code query supplied, return that specific promotion (with relations)
//     if ($request->filled('code')) {
//         $code = $request->query('code');
//         $promotion = Promotion::with('shop', 'orders')
//             ->where('code', $code)
//             ->firstOrFail();

//         return response()->json($promotion);
//     }

//     // otherwise return collection (with shop relation)
//     return Promotion::with('shop')->get();
// }
public function index(Request $request)
{
    // if code query supplied, return that specific promotion (with relations)
    if ($request->filled('code')) {
        $code = $request->query('code');
        $promotion = Promotion::with('shop')
            ->where('code', $code)
            ->firstOrFail();

        return response()->json($promotion);
    }

    // otherwise return collection (with shop relation)
    return Promotion::with('shop')->get();
}



    /**
     * Store a newly created promotion.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shopid'      => 'required|exists:shops,id',
            'code'        => 'required|string|max:30|unique:promotions,code',
            'type'        => 'required|in:percent,fixedamount',
            'value'       => 'required|integer|min:1',
            'startsat'    => 'required|date',
            'endsat'      => 'required|date|after:startsat',
            'isactive'    => 'boolean',
            'usagelimit'  => 'nullable|integer|min:1',
        ]);

        $promotion = Promotion::create($validated);

        return response()->json([
            'message' => 'Promotion created successfully.',
            'data' => $promotion
        ], 201);
    }

    /**
     * Display the specified promotion.
     */
    public function show($id)
    {
        $promotion = Promotion::with('shop', 'orders')->findOrFail($id);

        return $promotion;
    }


    //  Add new method to filter promotions by shopid, code, and name`

    //------------------------------------------------------------
    //GET /api/shop/promotions?shopid=1
    //GET /api/shop/promotions?shopid=1&code=SUMMER20
    //GET /api/shop/promotions?shopid=1&name=Summer
    //GET /api/shop/promotions?shopid=1&name=Summer&code=SUMMER20
    //------------------------------------------------------------
    
    public function showShopId(Request $request)
{
    // Validate required shopid
    $shopId = $request->query('shopid');
    if (empty($shopId)) {
        return response()->json([
            'message' => 'Missing required query parameter: shopid'
        ], 400);
    }

    // Optional filters
    $code = $request->query('code');
    $name = $request->query('name'); // <── new filter

    // Build query
    $query = Promotion::with('shop')
        ->where('shopid', (int) $shopId);

    // Filter by exact code
    if (!empty($code)) {
        $query->where('code', $code);
    }

    // Filter by name (supports partial match)
    if (!empty($name)) {
        $query->where('name', 'LIKE', "%$name%");
    }

    // Get results
    $promotions = $query->get();

    return response()->json($promotions);
    }


    /**
     * Update the specified promotion.
     */
    public function update(Request $request, $id)
    {
        $promotion = Promotion::findOrFail($id);

        $validated = $request->validate([
            'shopid'      => 'sometimes|exists:shops,id',
            'code'        => 'sometimes|string|max:30|unique:promotions,code,' . $promotion->id,
            'type'        => 'sometimes|in:percent,fixedamount',
            'value'       => 'sometimes|integer|min:1',
            'startsat'    => 'sometimes|date',
            'endsat'      => 'sometimes|date|after:startsat',
            'isactive'    => 'boolean',
            'usagelimit'  => 'nullable|integer|min:1',
        ]);

        $promotion->update($validated);

        return response()->json([
            'message' => 'Promotion updated successfully.',
            'data' => $promotion
        ]);
    }

    /**
     * Remove the specified promotion.
     */
    public function destroy($id)
    {
        $promotion = Promotion::findOrFail($id);
        $promotion->delete();

        return response()->json([
            'message' => 'Promotion deleted successfully.'
        ]);
    }
}
