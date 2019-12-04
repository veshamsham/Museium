<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Stories extends Model
{
    protected $table = 'stories';
    protected $fillable = [
        'user_id', 'stories_name', 'created_at', 'updated_at'
    ];
}