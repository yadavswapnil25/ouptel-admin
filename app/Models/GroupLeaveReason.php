<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupLeaveReason extends Model
{
    protected $table = 'Wo_Group_Leave_Reasons';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'group_id',
        'user_id',
        'reason',
        'time',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
