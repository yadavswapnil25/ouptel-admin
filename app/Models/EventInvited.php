<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventInvited extends Model
{
    use HasFactory;

    protected $table = 'Wo_Einvited';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'inviter_id',
        'invited_id',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inviter_id', 'user_id');
    }

    public function invited(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_id', 'user_id');
    }
}
