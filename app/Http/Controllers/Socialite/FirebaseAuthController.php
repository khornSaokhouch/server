<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;
use Kreait\Firebase\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class FirebaseAuthController extends Controller
{
    protected $auth;

    public function __construct()
    {
        // Initialize Firebase Auth
        $this->auth = (new Factory)
            ->withServiceAccount(base_path(env('FIREBASE_CREDENTIALS')))
            ->createAuth();
    }

    /**
     * Apple login with optional phone
     */
    public function appleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'phone' => 'nullable|string',
        ]);

        try {
            $verifiedToken = $this->auth->verifyIdToken($request->token);
            $firebaseUid = $verifiedToken->claims()->get('sub');
            $firebaseUser = $this->auth->getUser($firebaseUid);

            $email = $firebaseUser->email;
            $name = $firebaseUser->displayName ?? 'Apple User';
            $photoUrl = $firebaseUser->photoUrl ?? null;

            $user = User::where('firebase_uid', $firebaseUid)
                        ->orWhere(function ($query) use ($email) {
                            if ($email) $query->where('email', $email);
                        })
                        ->first();

            if (!$user) {
                $user = User::create([
                    'firebase_uid' => $firebaseUid,
                    'name' => $name,
                    'email' => $email,
                    'role' => 'customer',
                    'profile_image' => $photoUrl ? $this->storeProfileImage($photoUrl) : null,
                    'phone' => $request->phone ?? null,
                    'password' => Hash::make(Str::random(16)),
                ]);
            } else {
                // Update missing info
                $updated = false;
                if (!$user->firebase_uid) { $user->firebase_uid = $firebaseUid; $updated = true; }
                if (!$user->name && $name) { $user->name = $name; $updated = true; }
                if (!$user->email && $email) { $user->email = $email; $updated = true; }
                if (!$user->profile_image && $photoUrl) { $user->profile_image = $this->storeProfileImage($photoUrl); $updated = true; }
                if (!$user->phone && $request->phone) { $user->phone = $request->phone; $updated = true; }
                if ($updated) $user->save();
            }

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Apple login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_image' => $user->profile_image ? url($user->profile_image) : null,
                    'firebase_uid' => $user->firebase_uid,
                    'phone' => $user->phone,
                ],
                'token' => $token,
            ]);

        } catch (\Throwable $e) {
            Log::error('Apple login failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid token', 'message' => $e->getMessage()], 401);
        }
    }

    /**
     * Generic Firebase login
     */
    public function login(Request $request)
    {
        $request->validate(['id_token' => 'required|string']);

        try {
            $verifiedToken = $this->auth->verifyIdToken($request->id_token);
            $uid = $verifiedToken->claims()->get('sub');
            $firebaseUser = $this->auth->getUser($uid);

            $user = User::firstOrCreate(
                ['firebase_uid' => $uid],
                [
                    'name' => $firebaseUser->displayName ?? 'Firebase User',
                    'email' => $firebaseUser->email,
                    'phone' => $firebaseUser->phoneNumber,
                    'profile_image' => $firebaseUser->photoUrl,
                    'password' => Hash::make(Str::random(16)),
                    'role' => 'customer',
                ]
            );

            // Update missing info if needed
            $updateData = [];
            if ($firebaseUser->phoneNumber && $firebaseUser->phoneNumber !== $user->phone) $updateData['phone'] = $firebaseUser->phoneNumber;
            if ($firebaseUser->photoUrl && (!$user->profile_image || str_contains($user->profile_image, 'googleusercontent.com'))) $updateData['profile_image'] = $firebaseUser->photoUrl;

            if ($updateData) $user->update($updateData);

            // Ask for phone if missing
            if (!$user->phone) {
                $tempToken = Str::random(32);
                Cache::put('tempToken_' . $tempToken, $user->id, 1500); // 25 min

                return response()->json(['ok' => true, 'needs_phone' => true, 'tempToken' => $tempToken, 'user' => $user]);
            }

            $token = JWTAuth::fromUser($user);
            return response()->json(['ok' => true, 'needs_phone' => false, 'token' => $token, 'user' => $user]);

        } catch (\Kreait\Firebase\Exception\Auth\FailedToVerifyToken $e) {
            Log::error('Firebase token verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid Firebase token'], 401);
        } catch (\Throwable $e) {
            Log::error('Firebase login error: ' . $e->getMessage());
            return response()->json(['error' => 'Firebase login failed'], 500);
        }
    }

    /**
     * Update phone after Firebase login
     */
    public function updatePhone(Request $request)
    {
        $request->validate([
            'tempToken' => 'required|string',
            'phone' => 'required|string',
            'firebaseUid' => 'required|string',
        ]);

        $userId = Cache::get('tempToken_' . $request->tempToken);
        if (!$userId) return response()->json(['error' => 'Invalid or expired token'], 401);

        $user = User::find($userId);
        if (!$user) return response()->json(['error' => 'User not found'], 404);

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

    /**
     * Sign up by phone
     */
    public function signByPhone(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:30|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
                'id_token' => 'required|string',
            ]);

            $verifiedIdToken = $this->auth->verifyIdToken($request->id_token);
            $firebaseUid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $this->auth->getUser($firebaseUid);

            if ($firebaseUser->phoneNumber !== $request->phone) {
                return response()->json(['ok' => false, 'message' => 'Phone number mismatch with Firebase.'], 422);
            }

            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
                'role' => $request->role ?? 'customer',
                'firebase_uid' => $firebaseUid,
            ]);

            $token = JWTAuth::fromUser($user);

            // Telegram notification
            sendTelegramMessage(
                "🟢 *New User Registered*\nName: {$user->name}\nPhone: {$user->phone}\nRole: {$user->role}\nTime: " . now()->toDateTimeString()
            );

            return response()->json(['ok' => true, 'message' => 'User registered successfully!', 'user' => $user, 'token' => $token], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['ok' => false, 'message' => 'Validation failed.', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'Something went wrong.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store profile image locally
     */
    private function storeProfileImage($url)
    {
        try {
            $contents = file_get_contents($url);
            $filename = 'profile_' . Str::random(10) . '.jpg';
            Storage::disk('public')->put('profiles/' . $filename, $contents);
            return 'storage/profiles/' . $filename;
        } catch (\Exception $e) {
            Log::error('Failed to store profile image: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            JWTAuth::parseToken()->invalidate();
            return response()->json(['message' => 'Successfully logged out']);
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return response()->json(['error' => 'Token is invalid or already expired'], 401);
        }
    }
}
