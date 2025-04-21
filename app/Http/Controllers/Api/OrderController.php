<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Exception\ApiErrorException;

class OrderController extends Controller
{
    /**
     * Create a new order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'to_id' => 'required|exists:users,id',
            'order_type' => 'required|in:dinein,delivery,takeaway',
            'amount' => 'required|numeric|min:0',
            'delivery_fee' => 'required|numeric|min:0',
            'service_fee' => 'required|numeric|min:0',
            'cartItems' => 'required|array',
            'address' => 'required|string',
            'payment_method' => 'required|string',
            'lat' => 'required|string',
            'lng' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get the authenticated user
        $user = auth('api')->user();

        // Get chef's location
        $chef = User::find($request->to_id);
        if (!$chef || $chef->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Chef not found',
            ], 404);
        }

        // Generate a unique order number
        $orderNo = mt_rand(10000000, 99999999);

        // Create the order
        $order = Order::create([
            'order_no' => $orderNo,
            'order_type' => $request->order_type,
            'user_id' => $user->id,
            'to_id' => $request->to_id,
            'amount' => $request->amount,
            'delivery_fee' => $request->delivery_fee,
            'service_fee' => $request->service_fee,
            'cartItems' => $request->cartItems,
            'address' => $request->address,
            'payment_method' => $request->payment_method,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'chef_lat' => $chef->current_lat ?? '',
            'chef_lng' => $chef->current_lng ?? '',
            'status' => 'pending',
            'txn_id' => $request->txn_id ?? '',
            'timestamp' => now(),
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Order created successfully',
            'data' => $order,
        ], 201);
    }

    /**
     * Get current user's orders
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getUserOrders(Request $request)
    {
        $user = auth('api')->user();
        
        // Filter by status if provided
        $query = Order::where('user_id', $user->id);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Sort by date (default: newest first)
        $query->orderBy('created_at', $request->sort === 'oldest' ? 'asc' : 'desc');
        
        // Eager load relationships
        $query->with(['chef:id,first_name,last_name,image']);
        
        // Paginate results
        $perPage = $request->per_page ?? 10;
        $orders = $query->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $orders,
        ], 200);
    }

    /**
     * Get single order details
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getOrderDetails($id)
    {
        $user = auth('api')->user();
        
        // Find the order
        $order = Order::where('id', $id)
                    ->where(function($query) use ($user) {
                        // User can view their own orders or orders assigned to them as chef
                        $query->where('user_id', $user->id)
                              ->orWhere('to_id', $user->id);
                    })
                    ->with(['chef:id,first_name,last_name,image,phone,address'])
                    ->first();
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or you do not have permission to view it',
            ], 404);
        }
        
        // Check if user has left a review for this order
        $hasReview = Review::where('order_id', $order->id)
                          ->where('user_id', $user->id)
                          ->exists();
        
        // Format the response
        $orderData = [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'order_type' => $order->order_type,
            'amount' => $order->amount,
            'delivery_fee' => $order->delivery_fee,
            'service_fee' => $order->service_fee,
            'total_amount' => $order->amount + $order->delivery_fee + $order->service_fee,
            'status' => $order->status,
            'address' => $order->address,
            'payment_method' => $order->payment_method,
            'created_at' => $order->created_at,
            'cart_items' => $order->cartItems,
            'chef' => $order->chef ? [
                'id' => $order->chef->id,
                'name' => $order->chef->first_name . ' ' . $order->chef->last_name,
                'image' => $order->chef->image,
                'phone' => $order->chef->phone,
                'address' => $order->chef->address,
            ] : null,
            'has_review' => $hasReview,
        ];
        
        return response()->json([
            'success' => true,
            'data' => $orderData,
        ], 200);
    }

    /**
     * Create Stripe payment intent
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function createPaymentIntent(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Set your Stripe API key
            Stripe::setApiKey(config('services.stripe.secret'));
            
            // Create a payment intent
            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($request->amount * 100), // Amount in cents
                'currency' => $request->currency,
                'payment_method_types' => ['card'],
                'metadata' => [
                    'user_id' => auth('api')->id()
                ],
            ]);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id
                ],
            ], 200);
            
        } catch (ApiErrorException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stripe payment error: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add review to an order
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function addReview(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'detail' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = auth('api')->user();
        
        // Verify that the order belongs to the user
        $order = Order::where('id', $id)
                    ->where('user_id', $user->id)
                    ->where('status', 'completed') // Only completed orders can be reviewed
                    ->first();
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or cannot be reviewed',
            ], 404);
        }
        
        // Check if user has already reviewed this order
        $existingReview = Review::where('order_id', $order->id)
                              ->where('user_id', $user->id)
                              ->first();
        
        if ($existingReview) {
            return response()->json([
                'success' => false,
                'message' => 'You have already reviewed this dish for this order',
            ], 400);
        }
        
        // Create the review
        $review = Review::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'rest_id' => $order->to_id,
            'rating' => $request->rating,
            'detail' => $request->detail ?? '',
            'gallery' => $request->gallery ?? '',
            'timestamp' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Review added successfully',
            'data' => $review,
        ], 201);
    }
}