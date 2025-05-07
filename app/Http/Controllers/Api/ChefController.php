<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Dish;
use App\Models\Cuisine;
use App\Models\RestCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChefController extends Controller
{
    /**
     * Handle chef onboarding process (introduction, location, and availability)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function onboard(Request $request)
{
    // Get authenticated user
    $user = auth('api')->user();
    
    if (!$user) {
        return response()->json([
            'success' => false,
            'message' => 'User not authenticated'
        ], 401);
    }
    
    try {
       $updated = \DB::table('users')
        ->where('id', $user->id)
        ->update([
            // Introduction
            'about' => $request->about,
            
            // Location
            'address_name' => $request->address_name,
            'address' => $request->address,
            'address_detail' => $request->address_detail,
            'note' => $request->note,
            'current_lat' => $request->current_lat,
            'current_lng' => $request->current_lng,
            
            // Availability
            'availability_pickup' => $request->availability_pickup,
            'availability_delivery' => $request->availability_delivery,
            'availability_dinein' => $request->availability_dinein,
            'delivery_price' => $request->delivery_price,
            'dinein_price' => $request->dinein_price,
            'dinein_limit' => $request->dinein_limit,
            
            // Update user type to chef
            'user_type' => 'chef',
            
            // Set default status
            'rest_status' => 'available'
        ]);
        
        // Force reload user from database
    $user = \App\Models\User::find($user->id);
    
        return response()->json([
            'success' => true,
            'message' => 'Chef profile created successfully',
            'user' => $user
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to update profile',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Update chef status (available, busy, unavailable)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function updateStatus(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can update their status'
            ], 403);
        }
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:available,busy,unavailable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $updated = \DB::table('users')
        ->where('id', $user->id)
        ->update([
            // Set default status
            'rest_status' => $request->status
        ]);

        $user = \App\Models\User::find($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Chef status updated successfully',
            'status' => $user->rest_status,
            'user' => $user
        ], 200);
    }

    /**
     * Add a new dish or offer
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addDish(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        // if ($user->user_type !== 'chef') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Only chefs can add dishes'
        //     ], 403);
        // }
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'about' => 'required|string|max:255',
            'keywords' => 'nullable|array',
            'category' => 'nullable|string|max:100',
            'cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'price' => 'required|numeric|min:0',
            'images' => 'required|array',
            'sizes' => 'nullable|array',
            'dish_type' => 'required|in:dish,offer',
            // Optional fields for offers
            'ingredients' => 'nullable|string|max:255',
            'calories' => 'nullable|string|max:100',
            'portions' => 'nullable|string|max:100',
            'allergy' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create new dish or offer
        $dish = Dish::create([
            'user_id' => $user->id,
            'category' => $request->category,
            'cuisine_id' => $request->cuisine_id,
            'name' => $request->name,
            'about' => $request->about,
            'keywords' => json_encode($request->keywords),
            'price' => $request->price,
            'images' => json_encode($request->images),
            'sizes' => json_encode($request->sizes ?? []),
            'dish_type' => $request->dish_type,
            'ingredients' => $request->ingredients ?? '',
            'calories' => $request->calories ?? '',
            'portions' => $request->portions ?? '',
            'allergy' => $request->allergy ?? '',
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => $request->dish_type === 'offer' ? 'Offer added successfully' : 'Dish added successfully',
            'dish' => $dish
        ], 201);
    }

    /**
     * Get dishes for authenticated chef
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getDishes(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can view their dishes'
            ], 403);
        }

        // Set up query
        $query = Dish::where('user_id', $user->id);
        
        // Filter by dish_type if provided
        if ($request->has('dish_type') && in_array($request->dish_type, ['dish', 'offer'])) {
            $query->where('dish_type', $request->dish_type);
        }

        // Get dishes with pagination
        $perPage = $request->per_page ?? 10;
        $dishes = $query->with('cuisine')
                    ->orderBy('timestamp', 'desc')
                    ->paginate($perPage);

        // Get ratings for each dish
        $dishIds = $dishes->pluck('id')->toArray();
        $ratingsData = DB::table('reviews')
                        ->whereIn('dish_id', $dishIds)
                        ->select('dish_id', DB::raw('AVG(rating) as avg_rating'), DB::raw('COUNT(*) as reviews_count'))
                        ->groupBy('dish_id')
                        ->get()
                        ->keyBy('dish_id');

        // Add rating data to each dish
        $dishes->getCollection()->transform(function ($dish) use ($ratingsData) {
            if (isset($ratingsData[$dish->id])) {
                $dish->rating = round($ratingsData[$dish->id]->avg_rating, 1);
                $dish->reviews_count = $ratingsData[$dish->id]->reviews_count;
            } else {
                $dish->rating = 0;
                $dish->reviews_count = 0;
            }
            return $dish;
        });

        return response()->json([
            'success' => true,
            'data' => $dishes,
        ], 200);
    }

    /**
     * Update a dish or offer
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateDish(Request $request, $id)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        // if ($user->user_type !== 'chef') {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Only chefs can update dishes'
        //     ], 403);
        // }
        
        // Find the dish
        $dish = Dish::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
        
        if (!$dish) {
            return response()->json([
                'success' => false,
                'message' => 'Dish not found or you do not have permission to update it'
            ], 404);
        }
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'about' => 'nullable|string|max:255',
            'keywords' => 'nullable|array',
            'category' => 'nullable|string|max:100',
            'cuisine_id' => 'nullable|integer|exists:cuisines,id',
            'price' => 'nullable|numeric|min:0',
            'images' => 'nullable|array',
            'sizes' => 'nullable|array',
            'dish_type' => 'nullable|in:dish,offer',
            // Optional fields for offers
            'ingredients' => 'nullable|string|max:255',
            'calories' => 'nullable|string|max:100',
            'portions' => 'nullable|string|max:100',
            'allergy' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update fields that are provided in the request
        if ($request->has('name')) {
            $dish->name = $request->name;
        }
        
        if ($request->has('about')) {
            $dish->about = $request->about;
        }
        
        if ($request->has('keywords')) {
            $dish->keywords = json_encode($request->keywords);
        }
        
        if ($request->has('category')) {
            $dish->category = $request->category;
        }
        
        if ($request->has('cuisine_id')) {
            $dish->cuisine_id = $request->cuisine_id;
        }
        
        if ($request->has('price')) {
            $dish->price = $request->price;
        }
        
        if ($request->has('images')) {
            $dish->images = json_encode($request->images);
        }
        
        if ($request->has('sizes')) {
            $dish->sizes = json_encode($request->sizes);
        }
        
        if ($request->has('dish_type')) {
            $dish->dish_type = $request->dish_type;
        }
        
        if ($request->has('ingredients')) {
            $dish->ingredients = $request->ingredients;
        }
        
        if ($request->has('calories')) {
            $dish->calories = $request->calories;
        }
        
        if ($request->has('portions')) {
            $dish->portions = $request->portions;
        }
        
        if ($request->has('allergy')) {
            $dish->allergy = $request->allergy;
        }
        
        $dish->save();

        return response()->json([
            'success' => true,
            'message' => $dish->dish_type === 'offer' ? 'Offer updated successfully' : 'Dish updated successfully',
            'dish' => $dish
        ], 200);
    }

    /**
     * Delete a dish
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteDish($id)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can delete dishes'
            ], 403);
        }
        
        // Find the dish
        $dish = Dish::where('id', $id)
                  ->where('user_id', $user->id)
                  ->first();
        
        if (!$dish) {
            return response()->json([
                'success' => false,
                'message' => 'Dish not found or you do not have permission to delete it'
            ], 404);
        }
        
        // Delete the dish
        $dish->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dish deleted successfully'
        ], 200);
    }

    /**
     * Get chef orders with optional status filter
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChefOrders(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can view their orders'
            ], 403);
        }
        
        // Query builder for chef orders
        $query = \App\Models\Order::where('to_id', $user->id);
        
        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Get only active orders if specified
        if ($request->has('active') && $request->active == 'true') {
            $query->whereIn('status', ['pending', 'accepted', 'processing']);
        }
        
        // Sort by date (default: newest first)
        $query->orderBy('created_at', $request->sort === 'oldest' ? 'asc' : 'desc');
        
        // Eager load relationships
        $query->with(['user:id,first_name,last_name,image,phone,address']);
        
        // Paginate results
        $perPage = $request->per_page ?? 10;
        $orders = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $orders,
        ], 200);
    }

    /**
     * Update order status (accept/reject/process/complete)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function updateOrderStatus(Request $request, $id)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can update order status'
            ], 403);
        }
        
        // Validate request data
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'status' => 'required|string|in:accepted,rejected,processing,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the order
        $order = \App\Models\Order::where('id', $id)
                    ->where('to_id', $user->id)
                    ->first();
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you do not have permission to update it'
            ], 404);
        }
        
        // Check if order can be updated based on current status
        if ($order->status === 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update status of a cancelled order'
            ], 400);
        }

        if ($order->status === 'completed' && $request->status !== 'cancelled') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot update status of a completed order'
            ], 400);
        }
        
        // Update order status
        $order->status = $request->status;
        $order->save();
        
        // Add entry to order_history table
        \DB::table('order_history')->insert([
            'order_id' => $order->id,
            'status' => $request->status,
            'user_id' => $user->id,
            'timestamp' => now()
        ]);
        
        // Create notification for customer
        $statusMessage = '';
        switch ($request->status) {
            case 'accepted':
                $statusMessage = 'Your order has been accepted by the chef';
                break;
            case 'rejected':
                $statusMessage = 'Your order has been rejected by the chef';
                break;
            case 'processing':
                $statusMessage = 'Your Order has Started Cooking';
                break;
            case 'completed':
                $statusMessage = 'Your order is on the way';
                break;
            case 'cancelled':
                $statusMessage = 'Your order has been cancelled';
                break;
        }
        
        \App\Models\Notification::create([
            'order_id' => $order->id,
            'title' => 'Order Update',
            'notification' => $statusMessage,
            'user_id' => $order->user_id,
            'status' => $request->status,
            'rest_id' => $user->id,
            'type' => 'order',
            'seen' => 0
        ]);

        $notificationData = [
            'order_id' => $order->id,
            'order_status' => $order->status,
            'type' => 'order_update'
        ];
        
        sendPushNotification($order->user_id, 'Order Update', $statusMessage, $notificationData, 'user');
        
        return response()->json([
            'success' => true,
            'message' => 'Order status updated successfully',
            'order' => $order
        ], 200);
    }
    
    /**
     * Update chef bank details
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
   public function updateBankDetails(Request $request)
{
    $user = auth('api')->user();

    if ($user->user_type !== 'chef') {
        return response()->json([
            'success' => false,
            'message' => 'Only chefs can update bank details'
        ], 403);
    }

    $validator = Validator::make($request->all(), [
        'payment_method' => 'required|string|in:bank_transfer,paypal',
        'bank_details' => 'required|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'message' => 'Validation Error',
            'errors' => $validator->errors()
        ], 422);
    }

    $detailsJson = json_encode($request->bank_details);

    if ($request->payment_method === 'paypal') {
        $user->paypal_details = $detailsJson;
    } else {
        $user->bank_details = $detailsJson;
    }

    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Bank/PayPal details updated successfully',
        'user' => $user
    ], 200);
}


    /**
     * Create a withdraw request
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function requestWithdrawal(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can request withdrawals'
            ], 403);
        }
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user has bank details
        if (empty($user->bank_details)) {
            return response()->json([
                'success' => false,
                'message' => 'Please add bank details before requesting a withdrawal'
            ], 400);
        }

        // Check if user has enough balance
        if ($user->balance < $request->amount) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance',
                'available_balance' => $user->balance
            ], 400);
        }

        // Create withdrawal request
        $withdraw = new \App\Models\Withdraw();
        $withdraw->user_id = $user->id;
        $withdraw->amount = $request->amount;
        $withdraw->status = 'pending';
        $withdraw->save();

        // Deduct amount from user's balance
        $user->balance -= $request->amount;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request created successfully',
            'withdrawal' => $withdraw,
            'remaining_balance' => $user->balance,
            'user'=> $user
        ], 201);
    }

    /**
     * Get chef's withdrawal history
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getWithdrawals(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can view withdrawals'
            ], 403);
        }

        // Get withdrawals with pagination
        $perPage = $request->per_page ?? 10;
        $withdrawals = \App\Models\Withdraw::where('user_id', $user->id)
                            ->orderBy('created_at', 'desc')
                            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $withdrawals,
            'balance' => $user->balance
        ], 200);
    }
}