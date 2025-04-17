<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cuisine extends Model
{
    protected $table = 'cuisines';
    public $timestamps = false;
    
    protected $fillable = [
        'id', 'image', 'name'
    ];
    
    public function dishes()
    {
        return $this->hasMany(Dish::class, 'cuisine_id');
    }
}