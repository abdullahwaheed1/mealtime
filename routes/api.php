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

    // Test route to create random orders
    Route::get('/create-test-order/{chefId}', function ($chefId) {
        // Check if the chef exists
        $chef = App\Models\User::where('id', $chefId)
                            ->where('user_type', 'chef')
                            ->first();
        
        if (!$chef) {
            return response()->json([
                'success' => false,
                'message' => 'Chef not found'
            ], 404);
        }
        
        // Create a random customer if needed
        $customer = App\Models\User::where('user_type', 'customer')->inRandomOrder()->first();
        
        if (!$customer) {
            // If no customer exists, create one
            $customer = App\Models\User::create([
                'first_name' => 'Test',
                'last_name' => 'Customer',
                'email' => 'test'.time().'@example.com',
                'user_type' => 'customer',
                'password' => Hash::make('password123'),
                'timestamp' => now(),
            ]);
        }
        
        // Get some random dishes from this chef
        $dishes = App\Models\Dish::where('user_id', $chefId)->inRandomOrder()->take(rand(1, 3))->get();
        
        if ($dishes->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Chef has no dishes to order'
            ], 400);
        }
        
        // Create cart items
        $cartItems = [];
        $totalAmount = 0;
        
        foreach ($dishes as $dish) {
            $quantity = rand(1, 3);
            $itemPrice = $dish->price * $quantity;
            $totalAmount += $itemPrice;
            
            $cartItems[] = [
                'id' => $dish->id,
                'name' => $dish->name,
                'quantity' => $quantity,
                'price' => $dish->price,
                'total' => $itemPrice,
                'image' => $dish->images[0] ?? ''
            ];
        }
        
        // Random fees
        $deliveryFee = rand(2, 5);
        $serviceFee = round($totalAmount * 0.05, 2); // 5% service fee
        
        // Random address within Lahore
        $addresses = [
            'Block A, DHA Phase 5, Lahore',
            '25-B, Gulberg III, Lahore',
            'House 123, Street 7, Johar Town, Lahore',
            'Apartment 4B, Model Town, Lahore',
            'Shop 8, Liberty Market, Lahore'
        ];
        
        // Create order data
        $orderData = [
            'to_id' => $chefId,
            'order_type' => ['delivery', 'takeaway', 'dinein'][rand(0, 2)],
            'amount' => $totalAmount,
            'delivery_fee' => $deliveryFee,
            'service_fee' => $serviceFee,
            'cartItems' => $cartItems,
            'address' => $addresses[array_rand($addresses)],
            'payment_method' => ['cash', 'card', 'wallet'][rand(0, 2)],
            'lat' => '31.' . rand(100000, 999999),
            'lng' => '74.' . rand(100000, 999999),
        ];
        
        // Create a request instance with our data
        $request = new \Illuminate\Http\Request();
        $request->replace($orderData);
        
        // Set the authenticated user for this request
        Auth::guard('api')->setUser($customer);
        
        // Call the existing controller method
        $controller = new App\Http\Controllers\Api\OrderController();
        $response = $controller->createOrder($request);
        
        return $response;
    });
});