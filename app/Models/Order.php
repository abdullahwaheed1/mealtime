<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    public $timestamps = false;
    
    protected $fillable = [
        'id', 'order_no', 'order_type', 'user_id', 'to_id', 'amount', 'delivery_fee', 'service_fee',
        'cartItems', 'address', 'payment_method', 'lat', 'lng', 'chef_lat', 'chef_lng', 'status',
        'txn_id', 'timestamp', 'created_at'
    ];
    
    protected $dates = [
        'timestamp', 'created_at'
    ];
    
    // For the "cartItems" field which appears to be stored as JSON
    public function getCartItemsAttribute($value)
    {
        return json_decode($value, true);
    }
    
    public function setCartItemsAttribute($value)
    {
        $this->attributes['cartItems'] = json_encode($value);
    }
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function chef()
    {
        return $this->belongsTo(User::class, 'to_id');
    }
}