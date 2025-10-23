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

    public function login(Request $request)
    {
        $idToken = $request->input('id_token');

        if (!$idToken) {
            return response()->json(['error' => 'Missing ID token'], 400);
        }

        try {
            // Verify Firebase ID token
            $verifiedIdToken = $this->auth->verifyIdToken($idToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            // Get Firebase user details
            $firebaseUser = $this->auth->getUser($uid);
            Log::info('Firebase user', [
                'uid' => $firebaseUser->uid,
                'email' => $firebaseUser->email,
                'name' => $firebaseUser->displayName,
                'phone' => $firebaseUser->phoneNumber,
                'photo' => $firebaseUser->photoUrl,
            ]);
            

            $name = $firebaseUser->displayName ?? 'Firebase User';
            $email = $firebaseUser->email ?? null;
            $phone = $firebaseUser->phoneNumber ?? null;
            $profileImage = $firebaseUser->photoUrl ?? null;

            // 1️⃣ Try to find by Firebase UID
            $user = User::where('firebase_uid', $uid)->first();

            // 2️⃣ If not found, create new user
            if (!$user) {
                $user = User::create([
                    'firebase_uid' => $uid,
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'profile_image' => $profileImage,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'customer', // default role
                ]);
            } else {
                // 3️⃣ Update phone/profile if changed
                $user->update([
                    'phone' => $phone ?? $user->phone,
                    'profile_image' => $profileImage ?? $user->profile_image,
                ]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            // Optional: send Telegram notification if function exists
            if (function_exists('sendTelegramMessage')) {
                sendTelegramMessage("🟢 User logged in: {$user->name} ({$user->email}) ({$user->firebase_uid})");
            }

            // Return JSON
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_image' => $user->profile_image,
                    'firebase_uid' => $user->firebase_uid,
                    'role' => $user->role,
                ],
                'token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
            ]);

        } catch (\Throwable $e) {
            Log::error('Firebase login error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid Firebase token: ' . $e->getMessage()], 401);
        }
    }

    public function suginByPhone(Request $request, FirebaseAuth $firebaseAuth)
    {
        $idToken = $request->input('id_token');
    
        try {
            // ✅ Validate request
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:30|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
            ]);
    
            // ✅ Verify Firebase token
            try {
                $verifiedIdToken = $firebaseAuth->verifyIdToken($idToken);
            } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
                return response()->json([
                    'message' => 'Invalid Firebase token.',
                    'error' => $e->getMessage()
                ], 401);
            }
    
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
    
            // ✅ Optional: check if phone in Firebase matches the request phone
            $firebaseUser = $firebaseAuth->getUser($firebaseUid);
            if ($firebaseUser->phoneNumber !== $request->phone) {
                return response()->json([
                    'message' => 'Phone number mismatch with Firebase.'
                ], 422);
            }
    
            // ✅ Create user in local database
            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
                'role' => $request->role ?? 'customer',
                'firebase_uid' => $firebaseUid, // optional store Firebase UID
            ]);
    
            // ✅ Generate JWT token
            $token = JWTAuth::fromUser($user);
    
            // ✅ Optional: Telegram notification
            sendTelegramMessage("🟢 *New User Registered*\nName: {$user->name}\nPhone: {$user->phone}\nRole: {$user->role}\nTime: " . now()->toDateTimeString());
    
            return response()->json([
                'message' => 'User registered successfully!',
                'user' => $user,
                'token' => $token,
            ], 201);
    
        } catch (\Illuminate\Validation\ValidationException $e) {
            sendTelegramMessage("⚠️ Registration failed: {$e->getMessage()} (Phone: {$request->input('phone')})");
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            sendTelegramMessage("🔴 Registration error: " . $e->getMessage());
            return response()->json([
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
