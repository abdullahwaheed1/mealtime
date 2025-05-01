<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HomeController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ChefController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/set-password', [AuthController::class, 'setPassword']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/send-reset-code', [AuthController::class, 'sendResetCode']);
Route::post('/social-login', [AuthController::class, 'socialLogin']);

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::post('/register-device', [AuthController::class, 'registerDevice']);
    Route::post('/upload', [FileUploadController::class, 'upload']);
    Route::get('/home', [HomeController::class, 'index']);
    Route::get('/chefs', [HomeController::class, 'getChefs']);
    Route::get('/chef/{id}/dishes', [HomeController::class, 'getChefDishes']);
    Route::get('/chef/{id}/reviews', [HomeController::class, 'getChefReviews']);
    Route::get('/user', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/refresh', [AuthController::class, 'refresh']);

    Route::post('/dishes/{id}/like', [HomeController::class, 'toggleDishLike']);
    Route::post('/chefs/{id}/like', [HomeController::class, 'toggleChefLike']);

    Route::get('/chef/orders', [ChefController::class, 'getChefOrders']);
    Route::put('/chef/orders/{id}/status', [ChefController::class, 'updateOrderStatus']);

    Route::post('/chef/onboard', [ChefController::class, 'onboard']);
    Route::post('/chef/status', [ChefController::class, 'updateStatus']);

    // Dish management routes
    Route::post('/chef/dishes', [ChefController::class, 'addDish']);
    Route::get('/chef/dishes', [ChefController::class, 'getDishes']);
    Route::put('/chef/dishes/{id}', [ChefController::class, 'updateDish']);
    Route::delete('/chef/dishes/{id}', [ChefController::class, 'deleteDish']);

    // Chef bank details and withdrawal routes
    Route::post('/chef/bank-details', [ChefController::class, 'updateBankDetails']);
    Route::post('/chef/withdraw', [ChefController::class, 'requestWithdrawal']);
    Route::get('/chef/withdrawals', [ChefController::class, 'getWithdrawals']);

    // Order routes
    Route::post('/orders', [OrderController::class, 'createOrder']);
    Route::get('/orders', [OrderController::class, 'getUserOrders']);
    Route::get('/orders/{id}', [OrderController::class, 'getOrderDetails']);
    Route::post('/orders/{id}/review', [OrderController::class, 'addReview']);
    
    // Payment route
    Route::post('/payment/create-intent', [OrderController::class, 'createPaymentIntent']);
});