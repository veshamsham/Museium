<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Albums extends Model
{
    protected $table = 'albums';
    protected $fillable = [
        'user_id','stories_id','name', 'date', 'place', 'description', 'status',
    ];
}