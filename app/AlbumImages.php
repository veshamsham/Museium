<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AlbumImages extends Model
{
    protected $table = 'albumimages';
    protected $fillable = [
        'album_id', 'user_id', 'album_media_path'
    ];
}
