<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Models\Favourite;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    /**
     * Get homepage data
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Get cuisines list
        $cuisines = Cuisine::all();
        
        // Get recent 5 orders of the logged-in user
        $recentOrders = Order::where('user_id', $user->id)
                           ->orderBy('created_at', 'desc')
                           ->limit(5)
                           ->with('chef:id,first_name,last_name,image')
                           ->get();
        
        // Get top chefs (based on number of completed orders received)
        $topChefs = User::where('user_type', 'chef')
                     ->withCount(['chefOrders as completed_orders' => function($query) {
                         $query->where('status', 'completed');
                     }])
                     ->orderBy('completed_orders', 'desc')
                     ->limit(10)
                     ->get(['id', 'first_name', 'last_name', 'image', 'about']);
        
        // Get popular dishes (based on average rating from reviews)
        $popularDishes = Dish::leftJoin('reviews', 'dishes.id', '=', 'reviews.dish_id')
                         ->selectRaw('dishes.*, AVG(IFNULL(reviews.rating, 0)) as avg_rating')
                         ->groupBy('dishes.id')
                         ->orderBy('avg_rating', 'desc')
                         ->limit(10)
                         ->with('chef:id,first_name,last_name,image')
                         ->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'cuisines' => $cuisines,
                'recent_orders' => $recentOrders,
                'top_chefs' => $topChefs,
                'popular_dishes' => $popularDishes,
            ],
        ], 200);
    }
    
    /**
     * Get chefs with filters and sorting
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChefs(Request $request)
    {
        // Get authenticated user for like status
        $currentUserId = auth('api')->user() ? auth('api')->id() : null;
        
        // Start with a base query that selects specific columns
        $chefsQuery = User::where('user_type', 'chef')
            ->select([
                'users.id', 
                'users.first_name', 
                'users.last_name', 
                'users.image', 
                'users.about', 
                'users.current_lat', 
                'users.current_lng', 
                'users.rest_status', 
                'users.address', 
                'users.city'
            ]);
        
        // Add like status if user is authenticated
        if ($currentUserId) {
            $chefsQuery->selectRaw("(
                SELECT COUNT(*) > 0 
                FROM favourites 
                WHERE favourites.to_id = users.id 
                AND favourites.user_id = ? 
                AND favourites.like_type = 'users'
                AND favourites.status = 'like'
            ) as is_liked", [$currentUserId]);
        } else {
            $chefsQuery->selectRaw("false as is_liked");
        }
        
        // Add distance calculation if coordinates are provided
        if ($request->has('current_lat') && $request->has('current_lng')) {
            $lat = $request->current_lat;
            $lng = $request->current_lng;
            $radius = $request->radius ?? 10; // Default radius 10km
            
            $chefsQuery->selectRaw("(
                6371 * acos(
                    cos(radians(?)) * 
                    cos(radians(users.current_lat)) * 
                    cos(radians(users.current_lng) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(users.current_lat))
                )
            ) AS distance", [$lat, $lng, $lat])
            ->whereRaw("users.current_lat IS NOT NULL AND users.current_lng IS NOT NULL")
            ->having('distance', '<=', $radius);
        }
        
        // Filter by popularity
        if ($request->has('filter') && $request->filter === 'popular') {
            $chefsQuery->withCount(['chefOrders as order_count' => function($q) {
                $q->where('status', 'completed');
            }])
            ->orderBy('order_count', 'desc');
        }
        
        // Filter by top rated
        if ($request->has('filter') && $request->filter === 'top_rated') {
            $chefsQuery->leftJoin('reviews', 'users.id', '=', 'reviews.rest_id')
                ->selectRaw('AVG(IFNULL(reviews.rating, 0)) as avg_rating')
                ->groupBy([
                    'users.id', 'users.first_name', 'users.last_name', 'users.image', 
                    'users.about', 'users.current_lat', 'users.current_lng', 
                    'users.rest_status', 'users.address', 'users.city'
                ])
                ->orderBy('avg_rating', 'desc');
        }
        
        // Filter by open now
        if ($request->has('filter') && $request->filter === 'open_now') {
            $now = Carbon::now();
            $dayOfWeek = strtolower($now->format('l')); 
            $currentTime = $now->format('H:i');
            
            $chefsQuery->where('users.rest_status', 'available');
        }
        
        // Filter by cuisine
        if ($request->has('cuisine_id')) {
            $cuisineId = $request->cuisine_id;
            $chefsQuery->whereExists(function ($q) use ($cuisineId) {
                $q->select(DB::raw(1))
                    ->from('dishes')
                    ->whereRaw('dishes.user_id = users.id')
                    ->where('dishes.cuisine_id', $cuisineId);
            });
        }
        
        // Apply sorting
        if ($request->has('sort_by')) {
            switch ($request->sort_by) {
                case 'price_low':
                    $chefsQuery->leftJoin('dishes', 'users.id', '=', 'dishes.user_id')
                        ->groupBy([
                            'users.id', 'users.first_name', 'users.last_name', 'users.image', 
                            'users.about', 'users.current_lat', 'users.current_lng', 
                            'users.rest_status', 'users.address', 'users.city'
                        ])
                        ->orderBy(DB::raw('MIN(dishes.price)'), 'asc');
                    break;
                    
                case 'price_high':
                    $chefsQuery->leftJoin('dishes', 'users.id', '=', 'dishes.user_id')
                        ->groupBy([
                            'users.id', 'users.first_name', 'users.last_name', 'users.image', 
                            'users.about', 'users.current_lat', 'users.current_lng', 
                            'users.rest_status', 'users.address', 'users.city'
                        ])
                        ->orderBy(DB::raw('MAX(dishes.price)'), 'desc');
                    break;
                    
                case 'rating':
                    if (!($request->has('filter') && $request->filter === 'top_rated')) {
                        $chefsQuery->leftJoin('reviews', 'users.id', '=', 'reviews.rest_id')
                            ->selectRaw('AVG(IFNULL(reviews.rating, 0)) as avg_rating')
                            ->groupBy([
                                'users.id', 'users.first_name', 'users.last_name', 'users.image', 
                                'users.about', 'users.current_lat', 'users.current_lng', 
                                'users.rest_status', 'users.address', 'users.city'
                            ])
                            ->orderBy('avg_rating', 'desc');
                    }
                    break;
                    
                case 'distance':
                    if ($request->has('current_lat') && $request->has('current_lng')) {
                        $chefsQuery->orderBy('distance', 'asc');
                    }
                    break;
            }
        }
        
        // Paginate the results
        $perPage = $request->per_page ?? 10;
        $chefs = $chefsQuery->paginate($perPage);
        
        // Add calculated distance to each chef if coordinates were provided
        if ($request->has('current_lat') && $request->has('current_lng')) {
            $chefs->getCollection()->each(function ($chef) use ($request) {
                if (!isset($chef->distance) && !empty($chef->current_lat) && !empty($chef->current_lng)) {
                    $chef->distance = $this->calculateDistance(
                        $request->current_lat, 
                        $request->current_lng, 
                        $chef->current_lat, 
                        $chef->current_lng
                    );
                }
            });
        }
        
        return response()->json([
            'success' => true,
            'data' => $chefs,
        ], 200);
    }

    /**
     * Calculate distance between two points using Haversine formula
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in kilometers
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        if (empty($lat1) || empty($lng1) || empty($lat2) || empty($lng2)) {
            return null;
        }
        
        $earthRadius = 6371; // Radius of the earth in km
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
             sin($dLng/2) * sin($dLng/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        $distance = $earthRadius * $c; // Distance in km
        
        return round($distance, 2);
    }

    /**
     * Get all dishes of a chef with profile, cuisine and rating details
     *
     * @param  int  $id Chef ID
     * @return \Illuminate\Http\Response
     */
    public function getChefDishes($id)
    {
        // Get authenticated user for like status
        $currentUserId = auth('api')->user() ? auth('api')->id() : null;
        
        // Find the chef by ID
        $chef = User::where('id', $id)
                    ->where('user_type', 'chef')
                    ->first();
        
        if (!$chef) {
            return response()->json([
                'success' => false,
                'message' => 'Chef not found',
            ], 404);
        }
        
        // Check if current user likes this chef
        $chefIsLiked = false;
        if ($currentUserId) {
            $chefIsLiked = Favourite::where('user_id', $currentUserId)
                                            ->where('to_id', $chef->id)
                                            ->where('like_type', 'users')
                                            ->where('status', 'like')
                                            ->exists();
        }
        
        // Get chef profile details
        $chefProfile = [
            'id' => $chef->id,
            'first_name' => $chef->first_name,
            'last_name' => $chef->last_name,
            'image' => $chef->image,
            'about' => $chef->about,
            'address' => $chef->address,
            'city' => $chef->city,
            'rest_status' => $chef->rest_status,
            'is_liked' => $chefIsLiked,
        ];
        
        // Get all dishes of the chef
        $dishes = Dish::where('user_id', $chef->id)
                    ->with('cuisine')
                    ->get();
        
        $dishIds = $dishes->pluck('id')->toArray();
        
        // Get user's likes for dishes if authenticated
        $userLikes = [];
        if ($currentUserId) {
            $userLikes = Favourite::where('user_id', $currentUserId)
                                            ->where('like_type', 'dishes')
                                            ->where('status', 'like')
                                            ->whereIn('to_id', $dishIds)
                                            ->pluck('to_id')
                                            ->toArray();
        }
        
        // Get ALL ratings data in a single query for efficiency
        $ratingsData = DB::table('reviews')
                        ->whereIn('dish_id', $dishIds)
                        ->select('dish_id', 'rating')
                        ->get();
        
        // Process ratings data for each dish
        $dishRatingInfo = [];
        foreach ($dishIds as $dishId) {
            $dishRatingInfo[$dishId] = [
                'total' => 0,
                'sum' => 0,
                'counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
            ];
        }
        
        foreach ($ratingsData as $rating) {
            $dishId = $rating->dish_id;
            $ratingValue = $rating->rating;
            
            $dishRatingInfo[$dishId]['total']++;
            $dishRatingInfo[$dishId]['sum'] += $ratingValue;
            $dishRatingInfo[$dishId]['counts'][$ratingValue]++;
        }
        
        $dishesWithRatings = [];
        
        foreach ($dishes as $dish) {
            $dishId = $dish->id;
            $ratingInfo = $dishRatingInfo[$dishId] ?? ['total' => 0, 'sum' => 0, 'counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]];
            
            // Calculate average rating
            $avgRating = $ratingInfo['total'] > 0 ? $ratingInfo['sum'] / $ratingInfo['total'] : 0;
            
            // Prepare dish data with ratings and like status
            $dishData = [
                'id' => $dish->id,
                'name' => $dish->name,
                'about' => $dish->about,
                'price' => $dish->price,
                'images' => $dish->images,
                'cuisine' => $dish->cuisine ? [
                    'id' => $dish->cuisine->id,
                    'name' => $dish->cuisine->name,
                    'image' => $dish->cuisine->image,
                ] : null,
                'ratings' => [
                    'average' => round($avgRating, 1),
                    'total_reviews' => $ratingInfo['total'],
                    'star_counts' => $ratingInfo['counts'],
                ],
                'is_liked' => in_array($dish->id, $userLikes),
            ];
            
            $dishesWithRatings[] = $dishData;
        }
        
        // Get chef's overall rating stats
        $chefRatingsData = DB::table('reviews')
                            ->where('rest_id', $chef->id)
                            ->select('rating')
                            ->get();
        
        // Process chef ratings
        $chefRatingInfo = [
            'total' => 0,
            'sum' => 0,
            'counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
        ];
        
        foreach ($chefRatingsData as $rating) {
            $ratingValue = $rating->rating;
            
            $chefRatingInfo['total']++;
            $chefRatingInfo['sum'] += $ratingValue;
            $chefRatingInfo['counts'][$ratingValue]++;
        }
        
        // Calculate chef's average rating
        $chefAvgRating = $chefRatingInfo['total'] > 0 ? $chefRatingInfo['sum'] / $chefRatingInfo['total'] : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                'chef' => $chefProfile,
                'chef_ratings' => [
                    'average' => round($chefAvgRating, 1),
                    'total_reviews' => $chefRatingInfo['total'],
                    'star_counts' => $chefRatingInfo['counts'],
                ],
                'dishes' => $dishesWithRatings,
            ],
        ], 200);
    }

    /**
     * Get all reviews of a chef with pagination and optional rating filter
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id Chef ID
     * @return \Illuminate\Http\Response
     */
    public function getChefReviews(Request $request, $id)
    {
        // Find the chef by ID
        $chef = User::where('id', $id)
                    ->where('user_type', 'chef')
                    ->first();
        
        if (!$chef) {
            return response()->json([
                'success' => false,
                'message' => 'Chef not found',
            ], 404);
        }
        
        // Build the review query
        $reviewsQuery = Review::where('rest_id', $chef->id);
        
        // Filter by rating if specified
        if ($request->has('rating')) {
            $reviewsQuery->where('rating', $request->rating);
        }
        
        // Paginate the results
        $perPage = $request->per_page ?? 10;
        $reviews = $reviewsQuery->with(['user:id,first_name,last_name,image', 'dish:id,name,images'])
                            ->orderBy('timestamp', 'desc')
                            ->paginate($perPage);
        
        // Format the reviews for response
        $formattedReviews = [];
        foreach ($reviews as $review) {
            $formattedReviews[] = [
                'id' => $review->id,
                'rating' => $review->rating,
                'detail' => $review->detail,
                'gallery' => $review->gallery,
                'timestamp' => $review->timestamp,
                'user' => $review->user ? [
                    'id' => $review->user->id,
                    'name' => $review->user->first_name . ' ' . $review->user->last_name,
                    'image' => $review->user->image,
                ] : null,
                'dish' => $review->dish ? [
                    'id' => $review->dish->id,
                    'name' => $review->dish->name,
                    'image' => $review->dish->images ? $review->dish->images[0] : null,
                ] : null,
            ];
        }
        
        // Get chef's overall rating stats
        $chefRatingsData = DB::table('reviews')
                            ->where('rest_id', $chef->id)
                            ->select('rating')
                            ->get();
        
        // Process chef ratings
        $chefRatingInfo = [
            'total' => 0,
            'sum' => 0,
            'counts' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0]
        ];
        
        foreach ($chefRatingsData as $rating) {
            $ratingValue = $rating->rating;
            
            $chefRatingInfo['total']++;
            $chefRatingInfo['sum'] += $ratingValue;
            $chefRatingInfo['counts'][$ratingValue]++;
        }
        
        // Calculate chef's average rating
        $chefAvgRating = $chefRatingInfo['total'] > 0 ? $chefRatingInfo['sum'] / $chefRatingInfo['total'] : 0;
        
        return response()->json([
            'success' => true,
            'data' => [
                'chef_ratings' => [
                    'average' => round($chefAvgRating, 1),
                    'total_reviews' => $chefRatingInfo['total'],
                    'star_counts' => $chefRatingInfo['counts'],
                ],
                'reviews' => $formattedReviews,
                'pagination' => [
                    'total' => $reviews->total(),
                    'per_page' => $reviews->perPage(),
                    'current_page' => $reviews->currentPage(),
                    'last_page' => $reviews->lastPage(),
                    'from' => $reviews->firstItem(),
                    'to' => $reviews->lastItem(),
                ],
            ],
        ], 200);
    }

    /**
     * Like or unlike a dish
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Dish ID
     * @return \Illuminate\Http\Response
     */
    public function toggleDishLike(Request $request, $id)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if dish exists
        $dish = Dish::find($id);
        if (!$dish) {
            return response()->json([
                'success' => false,
                'message' => 'Dish not found',
            ], 404);
        }
        
        // Check if like already exists
        $favourite = Favourite::where('user_id', $user->id)
                                        ->where('to_id', $id)
                                        ->where('like_type', 'dishes')
                                        ->first();
        
        if ($favourite) {
            // Unlike - delete the record
            $favourite->delete();
            $isLiked = false;
        } else {
            // Like - create a new record
            Favourite::create([
                'user_id' => $user->id,
                'to_id' => $id,
                'like_type' => 'dishes',
                'status' => 'like',
                'datetime' => now(),
            ]);
            $isLiked = true;
        }
        
        return response()->json([
            'success' => true,
            'message' => $isLiked ? 'Dish liked successfully' : 'Dish unliked successfully',
            'is_liked' => $isLiked,
        ], 200);
    }

    /**
     * Like or unlike a chef
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id  Chef ID
     * @return \Illuminate\Http\Response
     */
    public function toggleChefLike(Request $request, $id)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Check if chef exists
        $chef = User::where('id', $id)->where('user_type', 'chef')->first();
        if (!$chef) {
            return response()->json([
                'success' => false,
                'message' => 'Chef not found',
            ], 404);
        }
        
        // Check if like already exists
        $favourite = Favourite::where('user_id', $user->id)
                                        ->where('to_id', $id)
                                        ->where('like_type', 'users')
                                        ->first();
        
        if ($favourite) {
            // Unlike - delete the record
            $favourite->delete();
            $isLiked = false;
        } else {
            // Like - create a new record
            Favourite::create([
                'user_id' => $user->id,
                'to_id' => $id,
                'like_type' => 'users',
                'status' => 'like',
                'datetime' => now(),
            ]);
            $isLiked = true;
        }
        
        return response()->json([
            'success' => true,
            'message' => $isLiked ? 'Chef liked successfully' : 'Chef unliked successfully',
            'is_liked' => $isLiked,
        ], 200);
    }
}