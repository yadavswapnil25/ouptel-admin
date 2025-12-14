<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupMember extends Model
{
    protected $table = 'Wo_Group_Members';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'user_id',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
