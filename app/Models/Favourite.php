<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favourite extends Model
{
    protected $table = 'favourites';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'to_id',
        'like_type',
        'status',
        'datetime',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'datetime' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function chef()
    {
        return $this->belongsTo(User::class, 'to_id')->where('like_type', 'users');
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class, 'to_id')->where('like_type', 'dishes');
    }
}