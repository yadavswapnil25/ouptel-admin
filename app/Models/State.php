<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    use HasFactory;

    protected $table = 'Wo_States';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'country_id',
        'name',
        'photo',
    ];
}

