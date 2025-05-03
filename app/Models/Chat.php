<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    protected $table = 'chat';
    public $timestamps = false;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'to_id',
        'order_id',
        'msg',
        'datetime',
        'msg_type',
        'seen',
        'timestamp'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'seen' => 'boolean',
        'msg_type' => 'integer',
        'datetime' => 'integer',
        'timestamp' => 'integer'
    ];

    /**
     * Get the user that sent the message.
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user that received the message.
     */
    public function receiver()
    {
        return $this->belongsTo(User::class, 'to_id');
    }

    /**
     * Get the order associated with the chat.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * Scope a query to only include messages for a specific order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $orderId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope a query to only include unseen messages.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeUnseen($query)
    {
        return $query->where('seen', 0);
    }

    /**
     * Mark the message as seen.
     *
     * @return bool
     */
    public function markAsSeen()
    {
        $this->seen = 1;
        return $this->save();
    }
}