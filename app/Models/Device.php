<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $table = 'devices';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'rider_id',
        'devicePlatform',
        'deviceRid',
        'deviceModel'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}