<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\ValidationException;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Cloudinary\Api\Upload\UploadApi;


/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for managing users"
 * )
 */

class UserController extends Controller
{
    /**
     * List all users
     */
    /**
     * @OA\Get(
     *     path="/api/admin/users",
     *     summary="List all users",
     *     tags={"Admin/users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Users retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="users",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="email", type="string"),
     *                     @OA\Property(property="phone", type="string"),
     *                     @OA\Property(property="role", type="string"),
     *                     @OA\Property(property="profile_image", type="string"),
     *                     @OA\Property(property="firebase_uid", type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized")
     * )
     */
    public function index(Request $request)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
    
            if (!$user->isAdmin()) {
                sendTelegramMessage("⚠️ Unauthorized access attempt by {$user->name} ({$user->profile_image})");
                return response()->json([
                    'message' => 'Unauthorized. Only admin can access this resource.'
                ], 403);
            }
    
            $users = User::all();
    
            // Prepare Telegram message with profile_image
            $userList = $users->map(function($u, $index) {
                $name = $u->name ?? 'N/A';
                $email = $u->email ?? 'N/A';
                $phone = $u->phone ?? 'N/A';
                $role = $u->role ?? 'N/A';
                $profileImage = $u->profile_image ?? 'N/A';
                return ($index + 1) . ". Name: {$name}, Email: {$email}, Phone: {$phone}, Role: {$role}, Profile: {$profileImage}";
            })->implode("\n");
    
            sendTelegramMessage("🟢 Admin {$user->name} retrieved all users at ".now().":\n{$userList}");
    
            // Include profile_image in JSON response
            $users = $users->map(function($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'phone' => $u->phone,
                    'role' => $u->role,
                    'profile_image' => $u->profile_image,
                    'firebase_uid' => $u->firebase_uid,
                ];
            });
    
            return response()->json([
                'message' => 'Users retrieved successfully',
                'users' => $users,
            ]);
    
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong', 
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Create new user
     */

    /**
     * @OA\Post(
     *     path="/api/users",
     *     summary="Create new user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","password","password_confirmation"},
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="password", type="string", format="password"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password"),
     *                 @OA\Property(property="role", type="string", enum={"customer","owner","admin"}),
     *                 @OA\Property(property="profile_image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|string|email|max:255|unique:users,email',
                'phone' => 'nullable|string|max:30|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
                'role' => 'sometimes|string|in:customer,owner,admin',
                'profile_image' => 'nullable|image|max:2048', // image validation
            ]);
    
            $profileImageUrl = null;
    
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
    
                $uploadedFile = (new UploadApi())->upload($file->getRealPath(), [
                    'folder' => 'users/profile_images',
                    'public_id' => Str::random(12),
                    'overwrite' => true,
                ]);
    
                $profileImageUrl = $uploadedFile['secure_url'];
            }
    
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => $request->role ?? 'customer',
                'profile_image' => $profileImageUrl,
                'remember_token' => Str::random(60),
            ]);
    
            return response()->json([
                'message' => 'User created successfully',
                'user' => $user,
            ], 201);
    
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    

    /**
     * Show single user
     */
     /**
     * @OA\Get(
     *     path="/api/users/{id}",
     *     summary="Show single user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="User retrieved successfully"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function show(Request $request, $id)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate() ?? User::find($id);

            if (!$user) {
                return response()->json(['message' => 'User not found or invalid token'], 404);
            }

            return response()->json([
                'message' => 'User retrieved successfully',
                'user' => $user,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Something went wrong', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update user
     */
    /**
     * @OA\Put(
     *     path="/api/users/{id}",
     *     summary="Update user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="email", type="string", format="email"),
     *                 @OA\Property(property="phone", type="string"),
     *                 @OA\Property(property="password", type="string", format="password"),
     *                 @OA\Property(property="password_confirmation", type="string", format="password"),
     *                 @OA\Property(property="profile_image", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="User updated successfully"),
     *     @OA\Response(response=404, description="User not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */ 
    public function update(Request $request, $id)
    {
        try {
            $user = User::find($id);
            if (!$user) {
                return response()->json(['message' => 'User not found'], 404);
            }
    
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
                'phone' => 'sometimes|string|max:30|unique:users,phone,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
                'profile_image' => 'nullable|image|max:2048',
            ]);
    
            $uploadApi = new UploadApi();
    
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
    
                // Delete old image if exists
                if ($user->profile_image) {
                    $parsedUrl = parse_url($user->profile_image, PHP_URL_PATH);
                    $pathParts = explode('/', ltrim($parsedUrl, '/'));
                    $publicIdWithExtension = end($pathParts);
                    $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);
    
                    try {
                        $uploadApi->destroy('users/profile_images/' . $publicId);
                    } catch (\Exception $e) {
                        // ignore if not found
                    }
                }
    
                // Upload new image
                $uploadedFile = $uploadApi->upload($file->getRealPath(), [
                    'folder' => 'users/profile_images',
                    'public_id' => Str::random(12),
                    'overwrite' => true,
                ]);
    
                $user->profile_image = $uploadedFile['secure_url'];
            }
    
            $user->name = $request->name ?? $user->name;
            $user->email = $request->email ?? $user->email;
            $user->phone = $request->phone ?? $user->phone;
    
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }
    
            $user->save();
            sendTelegramMessage("Update {$user->name} ({$user->profile_image})");
    
            return response()->json([
                'message' => 'User updated successfully',
                'user' => $user,
            ]);
    
        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Delete user
     */
       /**
     * @OA\Delete(
     *     path="/api/admin/users/{id}",
     *     summary="Delete user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="User ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(response=200, description="User deleted successfully"),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="User not found")
     * )
     */
    public function destroy($id)
    {
    try {
        $authUser = JWTAuth::parseToken()->authenticate();

        // Check if authenticated user is admin
        if (!$authUser->isAdmin()) {
            sendTelegramMessage("⚠️ Unauthorized delete attempt by {$authUser->name} ({$authUser->email})");
            return response()->json([
                'message' => 'Unauthorized. Only admin can delete users.'
            ], 403);
        }

        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Delete profile image from Cloudinary if it exists
        if ($user->profile_image) {
            $uploadApi = new UploadApi();
            $parsedUrl = parse_url($user->profile_image, PHP_URL_PATH);
            $pathParts = explode('/', ltrim($parsedUrl, '/'));
            $publicIdWithExtension = end($pathParts);
            $publicId = pathinfo($publicIdWithExtension, PATHINFO_FILENAME);

            try {
                $uploadApi->destroy('users/profile_images/' . $publicId);
            } catch (\Exception $e) {
                // Ignore if image not found or already deleted
            }
        }

        // Delete the user from database
        $user->delete();

        return response()->json([
            'message' => 'User and profile image deleted successfully'
        ]);

    } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
        return response()->json(['message' => 'JWT token expired'], 401);
    } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
        return response()->json(['message' => 'Invalid JWT token'], 401);
    } catch (\Tymon\JWTAuth\Exceptions\JWTException $e) {
        return response()->json(['message' => 'Token not provided'], 401);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Something went wrong',
            'error' => $e->getMessage(),
        ], 500);
    }
    }

    public function getByphonenumber(Request $request, $id){
          
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        // Return only the phone number
        return response()->json([
            'id' => $user->id,
            'phone' => $user->phone
        ], 200);

    }
}   
