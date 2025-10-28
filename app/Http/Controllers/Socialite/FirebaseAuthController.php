<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Dotenv\Exception\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Cache;
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

    // public function login(Request $request)
    // {
    //     $idToken = $request->input('id_token');

    //     if (!$idToken) {
    //         return response()->json(['error' => 'Missing ID token'], 400);
    //     }

    //     try {
    //         // Verify Firebase ID token
    //         $verifiedIdToken = $this->auth->verifyIdToken($idToken);
    //         $uid = $verifiedIdToken->claims()->get('sub');

    //         // Get Firebase user details
    //         $firebaseUser = $this->auth->getUser($uid);
    //         Log::info('Firebase user', [
    //             'uid' => $firebaseUser->uid,
    //             'email' => $firebaseUser->email,
    //             'name' => $firebaseUser->displayName,
    //             'phone' => $firebaseUser->phoneNumber,
    //             'photo' => $firebaseUser->photoUrl,
    //         ]);
            

    //         $name = $firebaseUser->displayName ?? 'Firebase User';
    //         $email = $firebaseUser->email ?? null;
    //         $phone = $firebaseUser->phoneNumber ?? null;
    //         $profileImage = $firebaseUser->photoUrl ?? null;

    //         // 1️⃣ Try to find by Firebase UID
    //         $user = User::where('firebase_uid', $uid)->first();

    //         // 2️⃣ If not found, create new user
    //         if (!$user) {
    //             $user = User::create([
    //                 'firebase_uid' => $uid,
    //                 'name' => $name,
    //                 'email' => $email,
    //                 'phone' => $phone,
    //                 'profile_image' => $profileImage,
    //                 'password' => Hash::make(Str::random(16)),
    //                 'role' => 'customer', // default role
    //             ]);
    //         } else {
    //             // 3️⃣ Update phone/profile if changed
    //             $user->update([
    //                 'phone' => $phone ?? $user->phone,
    //                 'profile_image' => $profileImage ?? $user->profile_image,
    //             ]);
    //         }

    //         // Generate JWT token
    //         $token = JWTAuth::fromUser($user);

    //         // Optional: send Telegram notification if function exists
    //         if (function_exists('sendTelegramMessage')) {
    //             sendTelegramMessage("🟢 User logged in: {$user->name} ({$user->email}) ({$user->firebase_uid})");
    //         }

    //         // Return JSON
    //         return response()->json([
    //             'user' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'phone' => $user->phone,
    //                 'profile_image' => $user->profile_image,
    //                 'firebase_uid' => $user->firebase_uid,
    //                 'role' => $user->role,
    //             ],
    //             'token' => $token,
    //             'token_type' => 'bearer',
    //             'expires_in' => auth('api')->factory()->getTTL() * 60,
    //         ]);

    //     } catch (\Throwable $e) {
    //         Log::error('Firebase login error: ' . $e->getMessage());
    //         return response()->json(['error' => 'Invalid Firebase token: ' . $e->getMessage()], 401);
    //     }
    // }


    // public function login(Request $request)
    // {
    //     $request->validate([
    //         'id_token' => 'required|string',
    //     ]);

    //     $idToken = $request->id_token;

    //     try {
    //         $auth = $this->firebase->getAuth();

    //         // Verify Firebase ID token
    //         $verifiedToken = $auth->verifyIdToken($idToken);
    //         $uid = $verifiedToken->claims()->get('sub');

    //         // Get Firebase user
    //         $firebaseUser = $auth->getUser($uid);

    //         $name = $firebaseUser->displayName ?? 'Firebase User';
    //         $email = $firebaseUser->email ?? null;
    //         $phone = $firebaseUser->phoneNumber ?? null;
    //         $profileImage = $firebaseUser->photoUrl ?? null;

    //         // Find or create user
    //         $user = User::firstOrCreate(
    //             ['firebase_uid' => $uid],
    //             [
    //                 'name' => $name,
    //                 'email' => $email,
    //                 'phone' => $phone,
    //                 'profile_image' => $profileImage,
    //                 'password' => Hash::make(Str::random(16)),
    //                 'role' => 'customer',
    //             ]
    //         );

    //         // Update phone or profile if changed
    //         $updateData = [];
    //         if ($phone && $phone !== $user->phone) $updateData['phone'] = $phone;
    //         if ($profileImage && $profileImage !== $user->profile_image) $updateData['profile_image'] = $profileImage;
    //         if (!empty($updateData)) $user->update($updateData);

    //         // If phone missing, generate tempToken for OTP verification
    //         if (!$user->phone) {
    //             $tempToken = Str::random(32);
    //             Cache::put('tempToken_' . $tempToken, $user->id, 900); // 15 min

    //             return response()->json([
    //                 'ok' => true,
    //                 'needs_phone' => true,
    //                 'tempToken' => $tempToken,
    //                 'user' => $user,
    //             ]);
    //         }

    //         // Generate JWT token
    //         $token = $user->createToken('app-token')->plainTextToken;

    //         return response()->json([
    //             'ok' => true,
    //             'needs_phone' => false,
    //             'token' => $token,
    //             'user' => $user,
    //         ]);

    //     } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
    //         Log::error('Firebase token verification failed: ' . $e->getMessage());
    //         return response()->json(['error' => 'Invalid Firebase token'], 401);
    //     } catch (\Throwable $e) {
    //         Log::error('Firebase login error: ' . $e->getMessage() . ' ' . $e->getTraceAsString());
    //         return response()->json(['error' => 'Firebase login failed: ' . $e->getMessage()], 500);
    //     }
    // }

    public function login(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $idToken = $request->id_token;

        try {
            // Use $this->auth instead of $this->firebase
            $verifiedToken = $this->auth->verifyIdToken($idToken);
            $uid = $verifiedToken->claims()->get('sub');

            $firebaseUser = $this->auth->getUser($uid);

            $name = $firebaseUser->displayName ?? 'Firebase User';
            $email = $firebaseUser->email ?? null;
            $phone = $firebaseUser->phoneNumber ?? null;
            $profileImage = $firebaseUser->photoUrl ?? null;

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

            $updateData = [];
            if ($phone && $phone !== $user->phone) $updateData['phone'] = $phone;
            if ($profileImage && $profileImage !== $user->profile_image) $updateData['profile_image'] = $profileImage;
            if (!empty($updateData)) $user->update($updateData);

            if (!$user->phone) {
                $tempToken = Str::random(32);
                Cache::put('tempToken_' . $tempToken, $user->id, 1500);

                return response()->json([
                    'ok' => true,
                    'needs_phone' => true,
                    'tempToken' => $tempToken,
                    'user' => $user,
                ]);
            }

            $token = JWTAuth::fromUser($user);

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

    // public function updatePhone(Request $request)
    // {
    //     $request->validate([
    //         'tempToken' => 'required|string',
    //         'phone' => 'required|string',
    //         'firebaseUid' => 'required|string',
    //     ]);

    //     $userId = Cache::get('tempToken_'.$request->tempToken);
    //     if (!$userId) return response()->json(['error' => 'Invalid or expired token'], 401);

    //     $user = User::find($userId);
    //     if (!$user) return response()->json(['error' => 'User not found'], 404);

    //     // Verify Firebase UID phone
    //     $auth = $this->firebase->getAuth();
    //     try {
    //         $record = $auth->getUser($request->firebaseUid);
    //         if ($record->phoneNumber !== $request->phone) {
    //             return response()->json(['error' => 'Phone verification failed'], 403);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['error' => 'Firebase verification failed'], 403);
    //     }

    //     $user->phone = $request->phone;
    //     $user->save();
    //     Cache::forget('tempToken_'.$request->tempToken);

    //     $token = $user->createToken('app-token')->plainTextToken;
    //     return response()->json(['ok' => true, 'token' => $token, 'user' => $user]);
    // }

    /**
     * Update phone after OTP verification
     */
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
