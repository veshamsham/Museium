<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Followers extends Model
{
    protected $fillable = [
        'following_id', 'follower_id', 'status',
    ];
    // 2 user A(1) and B(2) -------> A is following to B then following id = 2 and follower id = 1
}
