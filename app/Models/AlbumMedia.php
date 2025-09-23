<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlbumMedia extends Model
{
    protected $table = 'Wo_Albums_Media';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'post_id',
        'image',
    ];
}


