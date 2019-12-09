<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class NewsFeed extends Model
{
    protected $table = 'newsfeed';
    protected $fillable = [
        'user_id', 'note', 'description', 'stories_id', 'album_id', 'status','created_at','updated_at',
    ];
}