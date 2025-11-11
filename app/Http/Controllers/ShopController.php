<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\User;
use Cloudinary\Api\Upload\UploadApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

use Cloudinary\Configuration\Configuration; // Import the Configuration class

class ShopController extends Controller
{

    /**
     * Display all shops (sorted by latest).
     */
    public function index()
    {
        if (Auth::check() && Auth::user()->role === 'admin') {
            $shops = Shop::with('owner')->orderBy('id', 'desc')->get();
        } else {
            $shops = Shop::with('owner')
                ->where('status', '1')
                ->orderBy('id', 'desc')
                ->get();
        }
    
        // Add Google Maps URL dynamically
        $shops->transform(function ($shop) {
            $shop->google_map_url = ($shop->latitude && $shop->longitude && $shop->name)
                ? "https://www.google.com/maps/search/?api=1&query=" 
                  . urlencode($shop->name . " " . $shop->latitude . "," . $shop->longitude)
                : null;
            return $shop;
        });
        
    
        return response()->json([
            'message' => 'Shops retrieved successfully',
            'data' => $shops
        ]);
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
             'status' => 'nullable|in:0,1', // ✅ 1 = active, 0 = inactive
             'latitude' => 'nullable|numeric|between:-90,90',
             'longitude' => 'nullable|numeric|between:-180,180',
             'open_time' => 'nullable|date_format:H:i',
             'close_time' => 'nullable|date_format:H:i',
             'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
         ]);
     
         $validated['owner_user_id'] = $user->id;
     
         // ✅ Store image locally
         if ($request->hasFile('image')) {
             $file = $request->file('image');
     
             // Generate unique filename
             $fileName = 'shop_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
     
             // Store image in /storage/app/public/shops/images
             $filePath = $file->storeAs('shops/images', $fileName, 'public');
     
             // Store only relative path in DB
             $validated['image'] = $filePath;
         }
     
         // Default status to 1 (active) if not provided
         if (!isset($validated['status'])) {
             $validated['status'] = 1;
         }
     
         $shop = Shop::create($validated);
     
         return response()->json([
             'message' => 'Shop created successfully',
             'data' => $shop,
         ], 201);
     }
     
    
    /**
     * Display a specific shop.
     */
    public function show(Shop $shop)
    {
        // Load owner info along with the shop
        $shop->load('owner'); // eager load the owner relationship

        return response()->json([
            'message' => 'Shop fetched successfully',
            'data' => $shop
        ]);
    }

    /**
     * Update shop (only owner can update).
     */
    public function update(Request $request, Shop $shop)
    {
        $user = Auth::user();
    
        // Only shop owners can update
        if (!$this->isAuthorized($user, $shop)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        // User must own this shop
        if ($shop->owner_user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
    
        $validated = $request->validate([
            'name' => 'sometimes|string|max:150',
            'location' => 'nullable|string|max:255',
            'status' => 'sometimes|in:0,1', // 1 = active, 0 = inactive
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'open_time' => 'nullable|date_format:H:i',
            'close_time' => 'nullable|date_format:H:i',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);
    
         // Handle image update
         if ($request->hasFile('image')) {
            $this->deleteImage($shop->image); // Delete old image
            $validated['image'] = $this->uploadImage($request->file('image'), 'shops/images');
        }
    
        $shop->update($validated);
    
        return response()->json([
            'message' => 'Shop updated successfully',
            'data' => $shop
        ]);
    }
    

    /**
     * Delete shop (only owner can delete).
     */public function destroy(Shop $shop)
    {
        $user = Auth::user();
        
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Only owner or admin can delete
        if ($shop->owner_user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Delete shop image using helper
        $this->deleteImage($shop->image);

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

    private function isAuthorized($user, $shop)
    {
        return $user && ($user->isOwner() && $shop->owner_user_id === $user->id);
    }

        /**
     * Upload an image to the specified directory.
     */
    private function uploadImage($file, $directory)
    {
        $fileName = 'shop_' . Str::random(20) . '.' . $file->getClientOriginalExtension();
        return $file->storeAs($directory, $fileName, 'public');
    }

    private function deleteImage($path)
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

}