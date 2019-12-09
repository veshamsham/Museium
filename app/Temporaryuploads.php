<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Temporaryuploads extends Model
{
    protected $table = 'temporaryuploads';
    protected $fillable = [
        'user_id', 'file_path',
    ];
}
