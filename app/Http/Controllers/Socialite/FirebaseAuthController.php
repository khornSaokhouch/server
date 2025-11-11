<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Dotenv\Exception\ValidationException;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Kreait\Firebase\Auth as FirebaseAuth; 

class FirebaseAuthController extends Controller
{
    protected $auth;

    public function __construct()
    {
        // Load Firebase service account JSON
        $this->auth = (new Factory)
            ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
            ->createAuth();
    }
   
    public function appleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'phone' => 'nullable|string', // optional phone number
        ]);
    
        try {
            // 🔹 Verify Firebase ID Token
            $verifiedToken = $this->auth->verifyIdToken($request->token);
            $firebaseUid = $verifiedToken->claims()->get('sub');
    
            // 🔹 Get Firebase User Info
            $firebaseUser = $this->auth->getUser($firebaseUid);
            $email = $firebaseUser->email; // may be null
            $name = $firebaseUser->displayName ?? 'Apple User';
            $photoUrl = $firebaseUser->photoUrl ?? null;
    
            // 🔹 Find existing user by Firebase UID OR email
            $user = User::where('firebase_uid', $firebaseUid)
                        ->orWhere(function ($query) use ($email) {
                            if ($email) {
                                $query->where('email', $email);
                            }
                        })
                        ->first();
    
            // 🔹 Create new user if not exists
            if (!$user) {
                $user = User::create([
                    'firebase_uid' => $firebaseUid,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'customer',
                    'profile_image' => $photoUrl ? $this->storeProfileImage($photoUrl) : null,
                    'phone' => $request->phone ?? null,
                ]);
            } else {
                // 🔹 Update missing fields if needed
                $updated = false;
                if (!$user->firebase_uid) {
                    $user->firebase_uid = $firebaseUid;
                    $updated = true;
                }
                if (!$user->name && $name) {
                    $user->name = $name;
                    $updated = true;
                }
                if (!$user->email && $email) {
                    $user->email = $email;
                    $updated = true;
                }
                if (!$user->profile_image && $photoUrl) {
                    $user->profile_image = $this->storeProfileImage($photoUrl);
                    $updated = true;
                }
                if (!$user->phone && $request->phone) {
                    $user->phone = $request->phone;
                    $updated = true;
                }
                if ($updated) {
                    $user->save();
                }
            }
    
            // 🔹 Create JWT token
            $token = JWTAuth::fromUser($user);
    
            // 🔹 Return response
            return response()->json([
                'message' => 'Apple login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'profile_image' => $user->profile_image ? url($user->profile_image) : null,
                    'firebase_uid' => $user->firebase_uid,
                    'phone' => $user->phone,
                ],
                'token' => $token,
            ]);
    
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Invalid token',
                'message' => $e->getMessage(),
            ], 401);
        }
    }
    
    
    // public function appleLogin(Request $request)
    // {
    //     $request->validate([
    //         'token' => 'required|string',
    //     ]);
    
    //     try {
    //         // 1️⃣ Verify Firebase ID Token
    //         $verifiedToken = $this->auth->verifyIdToken($request->token);
    //         $firebaseUid = $verifiedToken->claims()->get('sub');
    
    //         // 2️⃣ Get Firebase User Info
    //         $firebaseUser = $this->auth->getUser($firebaseUid);
    //         $email = $firebaseUser->email ?? null;
    //         $displayName = $firebaseUser->displayName ?? 'Apple User'; // ✅ Use displayName
    //         $photoUrl = $firebaseUser->photoUrl ?? null;

    //         // 3️⃣ Find existing user by UID or email
    //         $user = User::where('firebase_uid', $firebaseUid)
    //                     ->orWhere('email', $email)
    //                     ->first();
    
    //         // 4️⃣ If user doesn't exist, create new
    //         if (!$user) {
    //             $user = User::create([
    //                 'name' => $displayName,
    //                 'email' => $email,
    //                 'firebase_uid' => $firebaseUid,
    //                 'role' => 'customer',
    //                'profile_image' => $photoUrl ? $this->storeProfileImage($photoUrl) : null,
    //             ]);
    //         }
    
    //         // 5️⃣ Generate JWT token
    //         $token = JWTAuth::fromUser($user);
    
    //         // 6️⃣ Return JSON response
    //         return response()->json([
    //             'message' => 'Apple login successful',
    //             'user' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'profile_image' => $user->profile_image ? url($user->profile_image) : null,
    //                 'firebase_uid' => $user->firebase_uid,
    //             ],
    //             'token' => $token,
    //         ]);
    //     } catch (\Throwable $e) {
    //         return response()->json([
    //             'error' => 'Invalid token',
    //             'message' => $e->getMessage(),
    //         ], 401);
    //     }
    // }
    
    private function storeProfileImage($url)
    {
        try {
            $contents = file_get_contents($url);
            $filename = 'profile_' . Str::random(10) . '.jpg';
            Storage::disk('public')->put('profiles/' . $filename, $contents);
            return 'storage/profiles/' . $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

     public function login(Request $request)
{
    $request->validate([
        'id_token' => 'required|string',
    ]);

    $idToken = $request->id_token;

    try {
        // Verify Firebase ID Token
        $verifiedToken = $this->auth->verifyIdToken($idToken);
        $uid = $verifiedToken->claims()->get('sub');

        // Get Firebase user info
        $firebaseUser = $this->auth->getUser($uid);

        $name = $firebaseUser->displayName ?? 'Firebase User';
        $email = $firebaseUser->email ?? null;
        $phone = $firebaseUser->phoneNumber ?? null;
        $profileImage = $firebaseUser->photoUrl ?? null;

        // Create or get user by Firebase UID
        $user = User::firstOrCreate(
            ['firebase_uid' => $uid],
            [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'profile_image' => $profileImage,
                'password' => Hash::make(Str::random(16)),
                'role' => 'customer',
            ]
        );

        // Prepare data for updating user info (only if needed)
        $updateData = [];

        // Update phone if changed or missing
        if ($phone && $phone !== $user->phone) {
            $updateData['phone'] = $phone;
        }

        /**
         * ✅ FIX: Only update the profile image if
         *  1. User doesn't have a custom one yet, or
         *  2. Existing image is still a Google profile image
         */
        if (
            $profileImage &&
            (
                !$user->profile_image ||
                str_contains($user->profile_image, 'googleusercontent.com')
            )
        ) {
            $updateData['profile_image'] = $profileImage;
        }

        // Apply updates if any
        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // If user still has no phone → ask for it
        if (!$user->phone) {
            $tempToken = Str::random(32);
            Cache::put('tempToken_' . $tempToken, $user->id, 1500); // 25 min

            return response()->json([
                'ok' => true,
                'needs_phone' => true,
                'tempToken' => $tempToken,
                'user' => $user,
            ]);
        }

        // Create JWT token for user
        $token = JWTAuth::fromUser($user);

        // Successful response
        return response()->json([
            'ok' => true,
            'needs_phone' => false,
            'token' => $token,
            'user' => $user,
        ]);
    } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
        Log::error('Firebase token verification failed: ' . $e->getMessage());
        return response()->json(['error' => 'Invalid Firebase token'], 401);
    } catch (\Throwable $e) {
        Log::error('Firebase login error: ' . $e->getMessage());
        return response()->json(['error' => 'Firebase login failed'], 500);
    }
    }
 

    public function updatePhone(Request $request)
{
    $request->validate([
        'tempToken' => 'required|string',
        'phone' => 'required|string',
        'firebaseUid' => 'required|string',
    ]);

    $userId = Cache::get('tempToken_' . $request->tempToken);
    if (!$userId) {
        return response()->json(['error' => 'Invalid or expired token'], 401);
    }

    $user = User::find($userId);
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // ✅ Use $this->auth instead of $this->firebase
    try {
        $record = $this->auth->getUser($request->firebaseUid);
        if ($record->phoneNumber !== $request->phone) {
            return response()->json(['error' => 'Phone verification failed'], 403);
        }
    } catch (\Exception $e) {
        return response()->json(['error' => 'Firebase verification failed: ' . $e->getMessage()], 403);
    }

    $user->phone = $request->phone;
    $user->save();
    Cache::forget('tempToken_' . $request->tempToken);

    $token = JWTAuth::fromUser($user);
    return response()->json(['ok' => true, 'token' => $token, 'user' => $user]);
}

    public function signByPhone(Request $request)
    {
        try {
            // Validate request
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:30|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
            ]);

            $idToken = $request->input('id_token');

            // Verify Firebase token
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');

            // Verify phone number matches Firebase
            $firebaseUser = $this->auth->getUser($firebaseUid);
            if ($firebaseUser->phoneNumber !== $request->phone) {
                return response()->json([
                    'ok' => false,
                    'message' => 'Phone number mismatch with Firebase.',
                ], 422);
            }

            // Create user
            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
                'role' => $request->role ?? 'customer',
                'firebase_uid' => $firebaseUid,
            ]);

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Send Telegram message
            sendTelegramMessage(
                "🟢 *New User Registered*\n".
                "Name: {$user->name}\n".
                "Phone: {$user->phone}\n".
                "Role: {$user->role}\n".
                "Time: " . now()->toDateTimeString()
            );

            // Success response
            return response()->json([
                'ok' => true,
                'message' => 'User registered successfully!',
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Validation errors
            return response()->json([
                'ok' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Throwable $e) {
            // Other errors
            return response()->json([
                'ok' => false,
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    

    // 🔹 Logout
    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();

            return response()->json([
                'message' => 'Successfully logged out'
            ]);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json([
                'error' => 'Token is invalid or already expired'
            ], 401);
        }
    }
}
