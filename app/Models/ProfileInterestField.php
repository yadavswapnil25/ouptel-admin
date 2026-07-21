<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileInterestField extends Model
{
    protected $table = 'Wo_Profile_Interest_Fields';

    protected $fillable = [
        'field_key',
        'label',
        'placeholder',
        'sort_order',
        'is_active',
        'storage_column',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
