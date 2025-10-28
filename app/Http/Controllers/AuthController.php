<?php

namespace App\Http\Controllers;

use App\Events\NewNotification;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Info(
 *     title="Auth API",
 *     version="1.0.0",
 *     description="API for User Authentication using JWT in Laravel"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT",
 *     description="Use a JWT token to access protected endpoints"
 * )
 */
class AuthController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/register",
     *     tags={"Authentication"},
     *     summary="Register a new user",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User registered successfully!"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'sometimes|string|in:customer,owner,admin',
        ]);
    
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role ?? 'customer', // ✅ assign role from request or default to cust
            'remember_token' => Str::random(60),
        ]);
    
        $token = JWTAuth::fromUser($user);
    
        // Send Telegram message
        sendTelegramMessage("🟢 *New User Registered*\nName: {$user->name}\nPhone: {$user->email}\nRole: {$user->role}\nTime: " . now()->toDateTimeString());

        return response()->json([
            'message' => 'User registered successfully!',
            'user' => $user,
            'token' => $token,
        ], 201);
    }


    /**
     * @OA\Post(
     *     path="/api/register-by-phone",
     *     tags={"Authentication"},
     *     summary="Register user by phone",
     *     description="Allows users to register using their phone number. Returns JWT token and remember_token.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","phone","password","password_confirmation"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="012345678"),
     *             @OA\Property(property="password", type="string", example="password123"),
     *             @OA\Property(property="password_confirmation", type="string", example="password123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User registered successfully!"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="remember_token", type="string")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=500, description="Internal server error")
     * )
     */
    public function registerByPhone(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:30|unique:users,phone',
                'password' => 'required|string|min:8|confirmed',
            ]);


            // ✅ Create user
            $user = User::create([
                'name' => $request->name,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'remember_token' => Str::random(60),
                'role' => $request->role ?? 'customer', // ✅ assign role from request or default to cust
            ]);

            // ✅ JWT Token
            $token = JWTAuth::fromUser($user);

            // ✅ Optional Telegram Notification
            sendTelegramMessage("🟢 *New User Registered*\nName: {$user->name}\nPhone: {$user->phone}\nRole: {$user->role}\nTime: " . now()->toDateTimeString());


            return response()->json([
                'message' => 'User registered successfully!',
                'user' => $user,
                'token' => $token,
            ], 201);

        } catch (ValidationException $e) {
            // ✅ Return validation errors in your preferred JSON format
            $phone = $request->input('phone');
            sendTelegramMessage("⚠️ Registration failed: {$e->getMessage()} (Phone: {$phone})");
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            // ❌ Catch any other unexpected errors
            sendTelegramMessage("🔴 Registration error: " . $e->getMessage());
            return response()->json([
                'message' => 'Something went wrong. Please try again later.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    
    /**
 * @OA\Post(
 *     path="/api/login",
 *     tags={"Authentication"},
 *     summary="Login user with email or phone",
 *     description="Allows users to log in using either their email or phone number. Returns JWT token and remember_token.",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"login","password"},
 *             @OA\Property(property="login", type="string", example="john@example.com"),
 *             @OA\Property(property="password", type="string", example="password123")
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Login successful",
 *         @OA\JsonContent(
 *             @OA\Property(property="message", type="string", example="Login successful"),
 *             @OA\Property(property="user", type="object",
 *                 @OA\Property(property="id", type="integer", example=1),
 *                 @OA\Property(property="name", type="string", example="John Doe"),
 *                 @OA\Property(property="email", type="string", example="john@example.com"),
 *                 @OA\Property(property="phone", type="string", example="012345678")
 *             ),
 *             @OA\Property(property="token", type="string", example="eyJ0eXAiOiJKV1QiLCJh..."),
 *             @OA\Property(property="remember_token", type="string", example="htOHStI24Bb19Qo9WHoSVRHfnrF5QcoSBlnJJb7ozuEWnnNouYERYabU7VaK")
 *         )
 *     ),
 *     @OA\Response(response=401, description="Invalid credentials"),
 *     @OA\Response(response=422, description="Validation failed")
 * )
 */
    public function login(Request $request)
    {
        try {
            // ✅ Validate input (accept email or phone)
            $request->validate([
                'login' => 'required|string', // can be email or phone
                'password' => 'required|string|min:6',
            ]);
    
            $loginInput = $request->input('login');
            $password = $request->input('password');
    
            // ✅ Determine login field type
            $field = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
    
            // ✅ Find user by email or phone
            $user = User::where($field, $loginInput)->first();
    
            if (!$user || !Hash::check($password, $user->password)) {
                sendTelegramMessage("🚫 Failed login attempt for: {$loginInput}");
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
    
            // ✅ Create JWT token
            $token = JWTAuth::fromUser($user);
    
            // ✅ Update remember_token
            $user->remember_token = Str::random(60);
            $user->save();
    
            // ✅ Send Telegram notification
            sendTelegramMessage("🔵 User logged in: {$user->name} ({$field}: {$loginInput})");
            // NewNotification::dispatch("Hello from Laravel! This is a test notification.");
    
            // ✅ Return success response
            return response()->json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user,
                'remember_token' => $user->remember_token,
            ]);
    
        } catch (ValidationException $e) {
            sendTelegramMessage("⚠️ Login validation failed: " . json_encode($e->errors()));
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
    
        } catch (\Exception $e) {
            sendTelegramMessage("🚨 Login error: {$e->getMessage()}");
            return response()->json([
                'message' => 'Something went wrong',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/me",
     *     tags={"Authentication"},
     *     summary="Get authenticated user info",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Authenticated user details",
     *         @OA\JsonContent(
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function me()
    {
        return response()->json(auth('api')->user());
    }


   /**
     * @OA\Post(
     *     path="/api/refresh",
     *     tags={"Authentication"},
     *     summary="Refresh JWT token",
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Token refreshed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Token refreshed successfully."),
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="expires_in", type="integer", example=3600)
     *         )
     *     ),
     *     @OA\Response(response=401, description="Token refresh failed")
     * )
     */
     
     public function refreshToken(Request $request)
     {
         try {
             // Try to authenticate user using the provided token
             $user = JWTAuth::parseToken()->authenticate();
     
             return response()->json([
                 'message' => 'Token is still valid',
                 'user' => $user,
                 'token' => (string) JWTAuth::getToken(),
                 'expires_in' => JWTAuth::factory()->getTTL() * 60,
                 'access_token_expiration' => Carbon::now()
                     ->addMinutes(JWTAuth::factory()->getTTL())
                     ->toDateTimeString(),
             ]);
         } 
         catch (TokenExpiredException $e) {
             // Token expired → try to refresh it
             try {
                 $newToken = JWTAuth::refresh(JWTAuth::getToken());
                 JWTAuth::setToken($newToken);
                 $user = JWTAuth::toUser($newToken);
     
                 // Optional: update remember_token each refresh
                 $user->remember_token = Str::random(60);
                 $user->save();
     
                 return response()->json([
                     'message' => 'Token refreshed successfully',
                     'user' => $user,
                     'token' => (string) $newToken,
                     'expires_in' => JWTAuth::factory()->getTTL() * 60,
                     'access_token_expiration' => Carbon::now()
                         ->addMinutes(JWTAuth::factory()->getTTL())
                         ->toDateTimeString(),
                 ]);
             } 
             catch (JWTException $e) {
                 return response()->json(['error' => 'Token cannot be refreshed'], 401);
             }
         } 
         catch (JWTException $e) {
             return response()->json(['error' => 'Token invalid or missing'], 401);
         }
     }



    //     public function sendOtp(Request $request)
    // {
    //     $request->validate([
    //         'phone' => 'required|string|unique:users,phone',
    //     ]);

    //     $otp = rand(100000, 999999); // 6-digit OTP
    //     $expiresAt = now()->addMinutes(5); // OTP valid for 5 minutes

    //     // Create user with phone only
    //     $user = User::create([
    //         'phone' => $request->phone,
    //         'otp_code' => $otp,
    //         'otp_expires_at' => $expiresAt,
    //     ]);

    //     // Send OTP (SMS or Telegram)
    //     sendTelegramMessage("Your OTP code is: {$otp}");

    //     return response()->json([
    //         'message' => 'OTP sent successfully',
    //         'phone' => $user->phone,
    //         'expires_at' => $expiresAt,
    //     ]);
    // }

    // public function sendOtp(Request $request)
    // {
    //     $request->validate([
    //         'phone' => 'required|string',
    //     ]);
    
    //     // 🔹 Normalize phone number
    //     $phone = preg_replace('/[^0-9]/', '', $request->phone); // remove non-numeric characters
    //     if (!str_starts_with($phone, '855')) {
    //         // ensure starts with Cambodia country code
    //         $phone = '855' . ltrim($phone, '0');
    //     }
    //     $phone = '+' . $phone;
    
    //     // 🔹 Generate OTP and expiration
    //     $otp = rand(100000, 999999);
    //     $expiresAt = now()->addMinutes(5);
    
    //     // 🔹 Create or update user
    //     $user = User::firstOrCreate(['phone' => $phone]);
    //     $user->update([
    //         'otp_code' => $otp,
    //         'otp_expires_at' => $expiresAt,
    //     ]);
    
    //     // 🔹 Message content
    //     $message = "Your OTP code is: {$otp}. It expires in 5 minutes.";
    
    //     // 🔹 Send via Telegram or fallback to SMS
    //     if (!empty($user->telegram_chat_id)) {
    //         sendTelegramMessage($user->telegram_chat_id, $message);
    //     } else {
    //         sendSms($phone, $message); // fallback to Twilio SMS
    //     }
    
    //     return response()->json([
    //         'message' => 'OTP sent successfully',
    //         'expires_at' => $expiresAt->toDateTimeString(),
    //         'phone' => $phone,
    //     ]);
    // }
    

    // public function verifyOtp(Request $request)
    // {
    //     $request->validate([
    //         'phone' => 'required|string',
    //         'otp_code' => 'required|string|size:6',
    //     ]);

    //     $user = User::where('phone', $request->phone)
    //                 ->where('otp_code', $request->otp_code)
    //                 ->where('otp_expires_at', '>', now())
    //                 ->first();

    //     if (!$user) {
    //         return response()->json(['error' => 'Invalid or expired OTP'], 401);
    //     }

    //     // Clear OTP after verification
    //     $user->otp_code = null;
    //     $user->otp_expires_at = null;
    //     $user->save();

    //     return response()->json([
    //         'message' => 'Phone verified successfully',
    //         'user_id' => $user->id,
    //     ]);
    // }


}
