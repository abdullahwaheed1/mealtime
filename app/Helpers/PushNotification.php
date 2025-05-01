<?php

namespace App\Helpers;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use App\Models\Device;

class PushNotification
{
    protected $messaging;

    public function __construct()
    {
        $firebase = (new Factory)
            ->withServiceAccount(storage_path('tomah-e3dc4-firebase-adminsdk-fbsvc-53d02e686b.json'))
            ->createMessaging();
            
        $this->messaging = $firebase;
    }

    /**
     * Send push notification to a specific device
     *
     * @param string $deviceToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool|array
     */
    public function sendToDevice($deviceToken, $title, $body, $data = [])
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('token', $deviceToken)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Push notification error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification to multiple devices
     *
     * @param array $deviceTokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToMultipleDevices($deviceTokens, $title, $body, $data = [])
    {
        $notification = Notification::create($title, $body);
        
        $messages = [];
        foreach ($deviceTokens as $token) {
            $messages[] = CloudMessage::withTarget('token', $token)
                ->withNotification($notification)
                ->withData($data);
        }

        try {
            $sendReport = $this->messaging->sendAll($messages);
            return [
                'success_count' => $sendReport->successes()->count(),
                'failure_count' => $sendReport->failures()->count(),
            ];
        } catch (\Exception $e) {
            \Log::error('Push notification error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Send push notification to a user's all devices
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendToUser($userId, $title, $body, $data = [])
    {
        $devices = Device::where('user_id', $userId)
                        ->whereNotNull('deviceRid')
                        ->where('deviceRid', '!=', '')
                        ->pluck('deviceRid')
                        ->toArray();

        if (empty($devices)) {
            return ['error' => 'No devices found for user'];
        }

        return $this->sendToMultipleDevices($devices, $title, $body, $data);
    }

    /**
     * Send push notification to specific topic
     *
     * @param string $topic
     * @param string $title
     * @param string $body
     * @param array $data
     * @return bool|array
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        try {
            $notification = Notification::create($title, $body);
            
            $message = CloudMessage::withTarget('topic', $topic)
                ->withNotification($notification)
                ->withData($data);

            $this->messaging->send($message);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('Push notification error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}