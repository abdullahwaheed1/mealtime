<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';
    
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'title',
        'notification',
        'user_id',
        'status',
        'timestamp',
        'rest_id',
        'type',
        'seen'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'timestamp' => 'datetime',
        'seen' => 'boolean',
    ];

    /**
     * Get the user that owns the notification.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the chef related to this notification.
     */
    public function chef()
    {
        return $this->belongsTo(User::class, 'rest_id');
    }

    /**
     * Get the order associated with the notification.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scope a query to only include unseen notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnseen($query)
    {
        return $query->where('seen', 0);
    }

    /**
     * Scope a query to only include order-related notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderType($query)
    {
        return $query->where('type', 'order');
    }

    /**
     * Scope a query to only include news notifications.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeNewsType($query)
    {
        return $query->where('type', 'news');
    }

    /**
     * Mark the notification as seen.
     *
     * @return bool
     */
    public function markAsSeen()
    {
        $this->seen = 1;
        return $this->save();
    }
}