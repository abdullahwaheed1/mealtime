<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    protected $table = 'withdraws';
    
    protected $fillable = [
        'user_id', 'amount', 'status'
    ];
    
    protected $dates = [
        'created_at', 'timestamp'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}