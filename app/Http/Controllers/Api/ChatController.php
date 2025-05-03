<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChatController extends Controller
{
    /**
     * Send a message
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'msg' => 'required|string|max:255',
            'msg_type' => 'sometimes|integer|in:0,1', // Optional, defaults to 0 (text)
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = auth('api')->user();

        // Get the order to find the recipient and verify authorization
        $order = Order::find($request->order_id);
        
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Verify that the user is authorized to send messages for this order
        if ($user->id != $order->user_id && $user->id != $order->to_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to send messages for this order'
            ], 403);
        }

        // Determine the recipient based on who sent the message
        // If sender is the customer, recipient is chef. If sender is chef, recipient is customer
        $toId = ($user->id == $order->user_id) ? $order->to_id : $order->user_id;

        // Create the chat message
        $chat = Chat::create([
            'user_id' => $user->id,
            'to_id' => $toId,
            'order_id' => $request->order_id,
            'msg' => $request->msg,
            'datetime' => 0, // Not used but keeping for DB structure compatibility
            'msg_type' => $request->msg_type ?? 0,
            'seen' => 0,
            'timestamp' => time()
        ]);

        // Send push notification to recipient
        try {
            $recipient = ($user->id == $order->user_id) ? $order->chef : $order->user;
            $senderName = $user->first_name . ' ' . $user->last_name;
            
            $notificationData = [
                'order_id' => $order->id,
                'type' => 'chat_message',
                'sender_id' => $user->id,
                'sender_name' => $senderName,
                'message' => $request->msg
            ];
            
            sendPushNotification(
                $toId, 
                'New Message - Order #' . $order->order_no, 
                $senderName . ': ' . $request->msg, 
                $notificationData, 
                'user'
            );
        } catch (\Exception $e) {
            // Log error but don't fail the request if notification fails
            \Log::error('Chat push notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => $chat
        ], 200);
    }

    /**
     * Get chat messages for a specific order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getChat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'per_page' => 'sometimes|integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = auth('api')->user();

        // Get the order to verify authorization
        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Verify that the user is authorized to view messages for this order
        if ($user->id != $order->user_id && $user->id != $order->to_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view messages for this order'
            ], 403);
        }

        // Get chat messages with pagination (sorted by timestamp ascending for chat-like experience)
        $perPage = $request->per_page ?? 20;
        $messages = Chat::where('order_id', $request->order_id)
                      ->orderBy('timestamp', 'asc')
                      ->with(['sender:id,first_name,last_name,image,user_type'])
                      ->paginate($perPage);

        // Mark messages as seen where current user is the recipient
        Chat::where('order_id', $request->order_id)
            ->where('to_id', $user->id)
            ->where('seen', 0)
            ->update(['seen' => 1]);

        // Transform messages to include sender info
        $messages->getCollection()->transform(function ($message) {
            return [
                'id' => $message->id,
                'msg' => $message->msg,
                'msg_type' => $message->msg_type,
                'seen' => $message->seen,
                'timestamp' => $message->timestamp,
                'is_mine' => $message->user_id == auth('api')->id(),
                'sender' => [
                    'id' => $message->sender->id,
                    'name' => $message->sender->first_name . ' ' . $message->sender->last_name,
                    'image' => $message->sender->image,
                    'user_type' => $message->sender->user_type
                ]
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $messages
        ], 200);
    }

    /**
     * Check for new messages (unread count)
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkNewMessages(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = auth('api')->user();

        // Get the order to verify authorization
        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Verify that the user is authorized to check messages for this order
        if ($user->id != $order->user_id && $user->id != $order->to_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to check messages for this order'
            ], 403);
        }

        // Count unread messages for the current user
        $unreadCount = Chat::where('order_id', $request->order_id)
                         ->where('to_id', $user->id)
                         ->where('seen', 0)
                         ->count();

        // Get the latest message if any
        $latestMessage = Chat::where('order_id', $request->order_id)
                           ->where('to_id', $user->id)
                           ->where('seen', 0)
                           ->orderBy('timestamp', 'desc')
                           ->with(['sender:id,first_name,last_name'])
                           ->first();

        // Transform latest message if exists
        $latestMessageData = null;
        if ($latestMessage) {
            $latestMessageData = [
                'id' => $latestMessage->id,
                'msg' => $latestMessage->msg,
                'timestamp' => $latestMessage->timestamp,
                'sender' => [
                    'id' => $latestMessage->sender->id,
                    'name' => $latestMessage->sender->first_name . ' ' . $latestMessage->sender->last_name
                ]
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
                'latest_message' => $latestMessageData
            ]
        ], 200);
    }

    /**
     * Mark messages as seen
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function markAsSeen(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user
        $user = auth('api')->user();

        // Get the order to verify authorization
        $order = Order::find($request->order_id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // Verify that the user is authorized
        if ($user->id != $order->user_id && $user->id != $order->to_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to mark messages as seen for this order'
            ], 403);
        }

        // Mark messages as seen
        $updatedCount = Chat::where('order_id', $request->order_id)
                          ->where('to_id', $user->id)
                          ->where('seen', 0)
                          ->update(['seen' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as seen',
            'updated_count' => $updatedCount
        ], 200);
    }
}