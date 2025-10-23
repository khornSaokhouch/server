<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Socialite\FirebaseAuthController;

use App\Http\Controllers\User\UserController as UserUserController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use TelegramController as GlobalTelegramController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// // Step 1: Phone input → Send OTP
// Route::post('/send-otp', [AuthController::class, 'sendOtp']);

// // Step 2: Verify OTP
// Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);

// Step 3:  registration (name, email, password)
Route::post('/register', [AuthController::class, 'register']);
    //Registration by number phone
 Route::post('/register-by-phone', [AuthController::class, 'registerByPhone']);

// Login


// Firebase login/logout
Route::post('/firebase-login', [FirebaseAuthController::class, 'login']);
Route::post('/firebase-logout', [FirebaseAuthController::class, 'logout'])->middleware('auth:api');

// Authenticated routes (require JWT token)
Route::group(['middleware' => ['auth:api']], function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
});

// // Test Telegram
// Route::get('/test-telegram', function () {
//     sendTelegramMessage("✅ Telegram bot is working!");
//     return "Message sent!";
// });

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| API Routes admin
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api'])->prefix('admin/users')->group(function () {
    Route::get('/', [UserUserController::class, 'index']);        // List all users (admin only)
    Route::get('/{id}', [UserUserController::class, 'show']);     // Show a specific user
    Route::put('/{id}', [UserUserController::class, 'update']);   // Update a user
    Route::delete('/{id}', [UserUserController::class, 'destroy']); // Delete a user
});


/*
|--------------------------------------------------------------------------
| API Routes User
|--------------------------------------------------------------------------
*/
Route::post('/users', [UserUserController::class, 'store']); // Create user (registration)
Route::get('/users/phone/{id}', [UserUserController::class, 'getByphonenumber']);


// Protected routes: require JWT auth
Route::middleware(['auth:api'])->prefix('users')->group(function () {

    Route::get('/', [UserUserController::class, 'index']);        // GET /api/users => List all users
    Route::get('/{id}', [UserUserController::class, 'show']);   // GET /api/users/{id?} => Show user by JWT, remember_token, or ID
    Route::put('/{id}', [UserUserController::class, 'update']);  // PUT /api/users/{id} => Update user
    Route::delete('/{id}', [UserUserController::class, 'destroy']); // DELETE /api/users/{id} => Delete user

});