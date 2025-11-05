<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Socialite\FirebaseAuthController;

use App\Http\Controllers\User\UserController as UserUserController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use TelegramController as GlobalTelegramController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\shopController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
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

// Route::post('/send-message', [MessageController::class, 'sendMessage']);

/*
|--------------------------------------------------------------------------
| API Routes Login  Firebase login/logout
|--------------------------------------------------------------------------
*/

Route::post('/firebase-login', [FirebaseAuthController::class, 'login']);
// 2️⃣ Update phone after OTP verification
Route::post('/update-phone', [FirebaseAuthController::class, 'updatePhone']);
Route::post("/sign-up-by-phone",[FirebaseAuthController::class, 'signByPhone' ]);
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


Route::middleware(['auth:api'])->prefix('admin/categories')->group(function () {
    Route::get('/', [CategoryController::class, 'index']);
    Route::post('/', [CategoryController::class, 'store']);
    Route::get('/{category}', [CategoryController::class, 'show']);
    Route::put('/{category}', [CategoryController::class, 'update']);
    Route::delete('/{category}', [CategoryController::class, 'destroy']);
});
Route::middleware(['auth:api'])->prefix('admin/shops')->group(function () {
    Route::get('/', [shopController::class, 'index']);        // List all shops
    Route::post('/', [shopController::class, 'store']);       // Create a new shop
    Route::get('/{shop}', [shopController::class, 'show']); // Show a specific shop
    Route::put('/{shop}', [shopController::class, 'update']); // Update a shop
    Route::delete('/{shop}', [shopController::class, 'destroy']); // Delete a shop
    Route::get('/nearby', [ShopController::class, 'nearby']);

});



Route::middleware(['auth:api'])->prefix('admin/items')->group(function () {
    Route::get('/', [ItemController::class, 'index']);        // List all items
    Route::post('/', [ItemController::class, 'store']);       // Create new item
    Route::get('/{item}', [ItemController::class, 'show']);   // Show specific item
    Route::post('/{item}', [ItemController::class, 'update']); // Update item
    Route::delete('/{item}', [ItemController::class, 'destroy']); // Delete item
});


Route::middleware(['auth:api'])->prefix('admin/carts')->group(function () {
    Route::get('/', [CartController::class, 'index']);        // List all carts
    Route::post('/', [CartController::class, 'store']);       // Create new cart
    Route::get('/{cart}', [CartController::class, 'show']);   // Show specific cart
    Route::put('/{cart}', [CartController::class, 'update']); // Update cart
    Route::delete('/{cart}', [CartController::class, 'destroy']); // Delete cart
});

Route::middleware(['auth:api'])->prefix('admin/cart-items')->group(function () {
    Route::post('/', [CartItemController::class, 'store']);       // Add item to cart
    Route::put('/{cartItem}', [CartItemController::class, 'update']); // Update cart item
    Route::delete('/{cartItem}', [CartItemController::class, 'destroy']); // Delete cart item
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
