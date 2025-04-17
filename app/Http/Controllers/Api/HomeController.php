<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cuisine;
use App\Models\Dish;
use App\Models\Order;
use App\Models\User;
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
            $dayOfWeek = strtolower($now->format('l')); // e.g. "monday"
            $currentTime = $now->format('H:i');
            
            $chefsQuery->where('users.rest_status', 'available');
            // Note: The JSON filtering for availability is complex and would need 
            // to be adapted based on your exact JSON structure
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
                    // Join dishes table but be explicit about columns
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
                    // Only add this join if not already added by top_rated filter
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
}