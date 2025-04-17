<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $table = 'reviews';
    public $timestamps = false;
    
    protected $fillable = [
        'id', 'user_id', 'order_id', 'rest_id', 'rating', 'detail', 'gallery', 'timestamp', 'dish_id'
    ];
    
    protected $dates = [
        'timestamp'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }
    
    public function chef()
    {
        return $this->belongsTo(User::class, 'rest_id');
    }
}