<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated user (customer or chef)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getNotifications(Request $request)
    {
        // Get authenticated user
        $user = auth('api')->user();
        
        // Set up query based on user type
        $query = Notification::query();
        
        if ($user->user_type === 'chef') {
            // For chefs, get notifications where rest_id is their ID
            $query->where('rest_id', $user->id);
        } else {
            // For customers, get notifications where user_id is their ID
            $query->where('user_id', $user->id);
        }
        
        // Filter by type if specified
        if ($request->has('type') && in_array($request->type, ['news', 'order'])) {
            $query->where('type', $request->type);
        }
        
        // Filter by seen status if specified
        if ($request->has('seen') && in_array($request->seen, ['0', '1'])) {
            $query->where('seen', $request->seen);
        }
        
        // For order type notifications, include order details
        $query->with(['order' => function ($query) {
            $query->select('id', 'order_no', 'status', 'amount', 'created_at');
        }]);
        
        // Paginate the results
        $perPage = $request->per_page ?? 20;
        $notifications = $query->orderBy('timestamp', 'desc')
                             ->paginate($perPage);
        
        // Mark notifications as seen if requested
        if ($request->has('mark_as_seen') && $request->mark_as_seen == '1') {
            // Only mark unseen notifications
            Notification::where('seen', 0)
                      ->when($user->user_type === 'chef', function ($query) use ($user) {
                          return $query->where('rest_id', $user->id);
                      })
                      ->when($user->user_type !== 'chef', function ($query) use ($user) {
                          return $query->where('user_id', $user->id);
                      })
                      ->update(['seen' => 1]);
        }
        
        // Count unseen notifications
        $unseenCount = Notification::where('seen', 0)
                                 ->when($user->user_type === 'chef', function ($query) use ($user) {
                                     return $query->where('rest_id', $user->id);
                                 })
                                 ->when($user->user_type !== 'chef', function ($query) use ($user) {
                                     return $query->where('user_id', $user->id);
                                 })
                                 ->count();
        
        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unseen_count' => $unseenCount
        ], 200);
    }
}