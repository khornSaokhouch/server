<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ShopController extends Controller
{
    /**
     * Display all shops (sorted by latest).
     */
    public function index()
{
    $user = Auth::user();

    // Admin sees all, others see only their own
    if ($user->role === 'admin') {
        $shops = Shop::orderBy('id', 'desc')->get();
    } else {
        $shops = Shop::where('owner_user_id', $user->id)
                     ->orderBy('id', 'desc')
                     ->get();
    }

    return response()->json($shops);
}


    /**
     * Store a newly created shop with authenticated user and geolocation.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'location' => 'nullable|string|max:255',
            'status' => 'required|in:active,inactive',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $validated['owner_user_id'] = $user->id;

        $shop = Shop::create($validated);

        return response()->json([
            'message' => 'Shop created successfully',
            'data' => $shop
        ], 201);
    }

    /**
     * Display a specific shop.
     */
    public function show(Shop $shop)
    {
        return response()->json($shop);
    }

    /**
     * Update shop (only owner can update).
     */
    public function update(Request $request, Shop $shop)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        if ($shop->owner_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'location' => 'nullable|string|max:255',
            'status' => 'sometimes|in:active,inactive',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        $shop->update($validated);

        return response()->json([
            'message' => 'Shop updated successfully',
            'data' => $shop
        ]);
    }

    /**
     * Delete shop (only owner can delete).
     */
    public function destroy(Shop $shop)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);
        
        // Allow delete if user is owner OR admin
        if ($shop->owner_user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        

        $shop->delete();

        return response()->json(['message' => 'Shop deleted successfully']);
    }

    /**
     * Find nearby shops based on user's coordinates.
     */
    public function nearby(Request $request)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|numeric|min:1', // radius in kilometers
        ]);

        $latitude = $validated['latitude'];
        $longitude = $validated['longitude'];
        $radius = $validated['radius'] ?? 10;

        // Haversine formula to calculate distance (in km)
        $shops = Shop::select('*')
            ->selectRaw("
                (6371 * acos(
                    cos(radians(?)) * cos(radians(latitude))
                    * cos(radians(longitude) - radians(?))
                    + sin(radians(?)) * sin(radians(latitude))
                )) AS distance
            ", [$latitude, $longitude, $latitude])
            ->having('distance', '<', $radius)
            ->orderBy('distance', 'asc')
            ->get();

        return response()->json([
            'message' => "Nearby shops within {$radius} km",
            'data' => $shops
        ]);
    }
}
