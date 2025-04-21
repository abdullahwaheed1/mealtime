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
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            // Introduction section
            'about' => 'required|string|max:255',
            
            // Location section
            'address' => 'required|string',
            'address_detail' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:255',
            'current_lat' => 'required|numeric',
            'current_lng' => 'required|numeric',
            
            // Availability section
            'availability_pickup' => 'required|json'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update user profile with chef details
        $user->update([
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
            
            // Update user type to chef
            'user_type' => 'chef',
            
            // Set default status
            'rest_status' => 'available'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chef profile created successfully',
            'user' => $user
        ], 200);
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

        // Update chef status
        $user->update([
            'rest_status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Chef status updated successfully',
            'status' => $user->rest_status
        ], 200);
    }

    /**
     * Add a new dish
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function addDish(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if user is a chef
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can add dishes'
            ], 403);
        }
        
        // Validate request data
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'about' => 'required|string|max:255',
            'keywords' => 'required|array',
            'category' => 'required|string|max:100',
            'cuisine_id' => 'required|integer|exists:cuisines,id',
            'price' => 'required|numeric|min:0',
            'images' => 'required|array',
            'delivery_price' => 'nullable|numeric|min:0',
            'dinein_price' => 'nullable|numeric|min:0',
            'dinein_limit' => 'nullable|integer|min:0',
            'sizes' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create new dish
        $dish = Dish::create([
            'user_id' => $user->id,
            'category' => $request->category,
            'cuisine_id' => $request->cuisine_id,
            'name' => $request->name,
            'about' => $request->about,
            'keywords' => json_encode($request->keywords),
            'price' => $request->price,
            'images' => json_encode($request->images),
            'delivery_price' => $request->delivery_price,
            'dinein_price' => $request->dinein_price,
            'dinein_limit' => $request->dinein_limit,
            'sizes' => json_encode($request->sizes ?? []),
            'timestamp' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dish added successfully',
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

        // Get dishes with pagination
        $perPage = $request->per_page ?? 10;
        $dishes = Dish::where('user_id', $user->id)
                    ->with('cuisine')
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
     * Update a dish
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
        if ($user->user_type !== 'chef') {
            return response()->json([
                'success' => false,
                'message' => 'Only chefs can update dishes'
            ], 403);
        }
        
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
            'delivery_price' => 'nullable|numeric|min:0',
            'dinein_price' => 'nullable|numeric|min:0',
            'dinein_limit' => 'nullable|integer|min:0',
            'sizes' => 'nullable|array',
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
        
        if ($request->has('delivery_price')) {
            $dish->delivery_price = $request->delivery_price;
        }
        
        if ($request->has('dinein_price')) {
            $dish->dinein_price = $request->dinein_price;
        }
        
        if ($request->has('dinein_limit')) {
            $dish->dinein_limit = $request->dinein_limit;
        }
        
        if ($request->has('sizes')) {
            $dish->sizes = json_encode($request->sizes);
        }
        
        $dish->save();

        return response()->json([
            'success' => true,
            'message' => 'Dish updated successfully',
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
}