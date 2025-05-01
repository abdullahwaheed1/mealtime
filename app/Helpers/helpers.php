<?php

use App\Helpers\PushNotification;

if (!function_exists('sendPushNotification')) {
    /**
     * Send push notification
     *
     * @param string|array $target Device token, user ID, or array of tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @param string $type 'device', 'user', 'multiple', or 'topic'
     * @return bool|array
     */
    function sendPushNotification($target, $title, $body, $data = [], $type = 'device')
    {
        $notification = new PushNotification();
        
        switch ($type) {
            case 'device':
                return $notification->sendToDevice($target, $title, $body, $data);
            case 'user':
                return $notification->sendToUser($target, $title, $body, $data);
            case 'multiple':
                return $notification->sendToMultipleDevices($target, $title, $body, $data);
            case 'topic':
                return $notification->sendToTopic($target, $title, $body, $data);
            default:
                return ['error' => 'Invalid notification type'];
        }
    }
}