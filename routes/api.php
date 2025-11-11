<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BroadcastController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\Socialite\FirebaseAuthController;
use App\Http\Controllers\User\UserController as UserUserController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiting\Limit;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartItemController;
use App\Http\Controllers\ItemOptionController;
use App\Http\Controllers\ItemOptionGroupAssignmentController;
use App\Http\Controllers\ItemOwnerController;
use App\Http\Controllers\Socialite\AppleidAuthController;
use App\Http\Controllers\ItemOptionGroupController;

/*
|--------------------------------------------------------------------------
| Rate Limiting Setup
|--------------------------------------------------------------------------
|
| Limit every user (or IP) to 60 requests per minute.
| If authenticated, limit by user_id; otherwise, by IP.
|
*/

RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
});

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Step 3: Registration
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:api');
Route::post('/register-by-phone', [AuthController::class, 'registerByPhone'])->middleware('throttle:api');

// Firebase Login
Route::post('/firebase-login', [FirebaseAuthController::class, 'login'])->middleware('throttle:api');
Route::post('/firebase/apple-login', [FirebaseAuthController::class, 'appleLogin'])->middleware('throttle:api');
Route::post('/update-phone', [FirebaseAuthController::class, 'updatePhone'])->middleware('throttle:api');
Route::post('/sign-up-by-phone', [FirebaseAuthController::class, 'signByPhone'])->middleware('throttle:api');
Route::post('/firebase-logout', [FirebaseAuthController::class, 'logout'])->middleware(['auth:api', 'throttle:api']);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:api');

// Authenticated routes
Route::group(['middleware' => ['auth:api', 'throttle:api']], function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refreshToken']);
});

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'throttle:api'])->prefix('admin')->group(function () {

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserUserController::class, 'index']);
        Route::get('/{id}', [UserUserController::class, 'show']);
        Route::put('/{id}', [UserUserController::class, 'update']);
        Route::delete('/{id}', [UserUserController::class, 'destroy']);
    });

    // Categories
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    // Shops
    Route::prefix('shops')->group(function () {
        Route::get('/', [ShopController::class, 'index']);
        Route::post('/', [ShopController::class, 'store']);
        Route::get('/nearby', [ShopController::class, 'nearby']);
        Route::get('/{shop}', [ShopController::class, 'show']);
        Route::put('/{shop}', [ShopController::class, 'update']);
        Route::delete('/{shop}', [ShopController::class, 'destroy']);
    });

    // Items
    Route::prefix('items')->group(function () {
        Route::get('/', [ItemController::class, 'index']);
        Route::post('/', [ItemController::class, 'store']);
        Route::get('/{id}', [ItemController::class, 'show']);
        Route::get('/category/{categoryId}', [ItemController::class, 'showAllByCategory']);
        Route::post('/{item}', [ItemController::class, 'update']);
        Route::delete('/{item}', [ItemController::class, 'destroy']);
    });

    // Item Option Groups
    Route::prefix('item-option-groups')->group(function () {
        Route::get('/', [ItemOptionGroupController::class, 'index']);
        Route::post('/', [ItemOptionGroupController::class, 'store']);
        Route::get('/{id}', [ItemOptionGroupController::class, 'show']);
        Route::put('/{id}', [ItemOptionGroupController::class, 'update']);
        Route::delete('/{id}', [ItemOptionGroupController::class, 'destroy']);
    });

     // Item Options
     Route::prefix('item-options')->group(function () {
        Route::get('/', [ItemOptionController::class, 'index']);     // List all options
        Route::post('/', [ItemOptionController::class, 'store']);    // Create new option
        Route::get('/group/{groupId}', [ItemOptionController::class, 'getByGroup']);
        Route::get('/{id}', [ItemOptionController::class, 'show']);  // Get single option
        Route::put('/{id}', [ItemOptionController::class, 'update']); // Update option
        Route::delete('/{id}', [ItemOptionController::class, 'destroy']); // Delete option
    });
    

    // Carts
    Route::prefix('carts')->group(function () {
        Route::get('/', [CartController::class, 'index']);
        Route::post('/', [CartController::class, 'store']);
        Route::get('/{cart}', [CartController::class, 'show']);
        Route::put('/{cart}', [CartController::class, 'update']);
        Route::delete('/{cart}', [CartController::class, 'destroy']);
    });

    // Cart Items
    Route::prefix('cart-items')->group(function () {
        Route::post('/', [CartItemController::class, 'store']);
        Route::put('/{cartItem}', [CartItemController::class, 'update']);
        Route::delete('/{cartItem}', [CartItemController::class, 'destroy']);
    });
});

/*
|--------------------------------------------------------------------------
| Shop Routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'throttle:api'])->prefix('shop')->group(function () {
    
    // Shops
    Route::get('/', [ItemOwnerController::class, 'index']);
    Route::get('/{shop_id}/categories', [ItemOwnerController::class, 'categoriesByOwner']);
    
    // Items
    Route::get('/{shop_id}/items', [ItemOwnerController::class, 'itemsByOwnerAndCategory']);
    Route::post('/item', [ItemOwnerController::class, 'store']);
    Route::patch('/{id}/status', [ItemOwnerController::class, 'updateStatus']);
    Route::delete('/{id}', [ItemOwnerController::class, 'destroy']);

    // Item-Option Group assignments
    Route::get('/item-option-groups', [ItemOptionGroupAssignmentController::class, 'index']);
    Route::post('/item-option-groups', [ItemOptionGroupAssignmentController::class, 'store']);
    Route::get('/item-option-groups/{itemId}', [ItemOptionGroupAssignmentController::class, 'show']);
    Route::delete('/item-option-groups', [ItemOptionGroupAssignmentController::class, 'destroy']);
    
});


/*
|--------------------------------------------------------------------------
| User Routes
|--------------------------------------------------------------------------
*/
Route::post('/users', [UserUserController::class, 'store'])->middleware('throttle:api');
Route::get('/users/phone/{id}', [UserUserController::class, 'getByphonenumber'])->middleware('throttle:api');

Route::middleware(['auth:api', 'throttle:api'])->prefix('users')->group(function () {
    Route::get('/', [UserUserController::class, 'index']);
    Route::get('/{id}', [UserUserController::class, 'show']);
    Route::put('/{id}', [UserUserController::class, 'update']);
    Route::delete('/{id}', [UserUserController::class, 'destroy']);

    // Get items by shop and category
    Route::get('/{shop_id}/items', [ItemOwnerController::class, 'itemsByOwnerAndCategory']);

});

/*
|--------------------------------------------------------------------------
| Public Shop Routes
|--------------------------------------------------------------------------
*/
Route::prefix('shops')->middleware('throttle:api')->group(function () {
    Route::get('/', [ShopController::class, 'index']);
    Route::get('/nearby', [ShopController::class, 'nearby']);
    Route::get('/{shop_id}/items', [ItemOwnerController::class, 'itemsByOwnerAndCategory']);
    Route::get('/{shop}', [ShopController::class, 'show']);

});
