<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dish extends Model
{
    protected $table = 'dishes';
    public $timestamps = false;
    
    protected $fillable = [
        'id', 'user_id', 'cat_id', 'cuisine_id', 'images', 'keywords', 'name', 'about', 'price', 
        'delivery_price', 'dinein_price', 'dinein_limit', 'sizes', 'timestamp'
    ];
    
    public function cuisine()
    {
        return $this->belongsTo(Cuisine::class, 'cuisine_id');
    }
    
    public function chef()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function reviews()
    {
        return $this->hasMany(Review::class, 'dish_id');
    }
    
    // For the "images" field which appears to be stored as JSON
    public function getImagesAttribute($value)
    {
        return json_decode($value, true);
    }
    
    // For the "keywords" field which appears to be stored as JSON
    public function getKeywordsAttribute($value)
    {
        return json_decode($value, true);
    }
    
    // For the "sizes" field which appears to be stored as JSON
    public function getSizesAttribute($value)
    {
        return json_decode($value, true);
    }
}