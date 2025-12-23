<?php

namespace App\Http\Controllers\Socialite;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\InvalidToken;

use Lcobucci\JWT\Token\InvalidTokenStructure;
use Tymon\JWTAuth\Facades\JWTAuth;

class AppleidAuthController extends Controller
{
    protected $auth;
    public function __construct()
    {
        $this->auth = (new Factory)
            ->withServiceAccount(env('FIREBASE_CREDENTIALS'))
            ->createAuth();
    }
    

    /**
     * Apple Login
     */
    public function appleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            // Verify Firebase ID Token
            $verifiedToken = $this->auth->verifyIdToken($request->token);
            $firebaseUid = $verifiedToken->claims()->get('sub');

            // Get Firebase User Info
            $firebaseUser = $this->auth->getUser($firebaseUid);
            $email = $firebaseUser->email ?? null;
            $name = $firebaseUser->displayName ?? 'Apple User';
            $photoUrl = $firebaseUser->photoUrl ?? null;
            $phone = $request->phone ?? null;

            // Find or create user
            $user = User::where('firebase_uid', $firebaseUid)
                ->when($email, function ($query) use ($email) {
                    $query->orWhere('email', $email);
                })
                ->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'name' => $name,
                    'email' => $email ?? $user->email,
                    'phone' => $phone ?? $user->phone,
                    'profile_image' => $photoUrl ? $this->storeProfileImage($photoUrl) : ($user->profile_image ?? null),
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'firebase_uid' => $firebaseUid,
                    'role' => 'customer',
                    'password' => bcrypt(Str::random(16)),
                    'profile_image' => $photoUrl ? $this->storeProfileImage($photoUrl) : null,
                ]);
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'message' => 'Apple login successful',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'profile_image' => $user->profile_image ? url($user->profile_image) : null,
                    'firebase_uid' => $user->firebase_uid,
                    'role' => $user->role,
                ],
                'token' => $token,
            ]);
        } catch (InvalidTokenStructure $e) {
            Log::error('Invalid Firebase token: ' . $e->getMessage());
            return response()->json([
                'error' => 'Invalid token',
                'message' => 'The provided Firebase ID token is invalid or expired.',
                'details' => $e->getMessage(),
            ], 401);
        } catch (\Throwable $e) {
            Log::error('Apple login error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Something went wrong',
                'message' => 'An unexpected error occurred during Apple login.',
                'details' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store profile image from URL
     */
    private function storeProfileImage($url)
    {
        try {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return null;
            }

            $contents = file_get_contents($url);
            $filename = 'profile_' . Str::random(12) . '.jpg';
            $path = 'profiles/' . $filename;

            Storage::disk('public')->put($path, $contents);

            return 'storage/' . $path;
        } catch (\Exception $e) {
            Log::error("Failed to store profile image from URL: {$url}. Error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Logout
     */
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