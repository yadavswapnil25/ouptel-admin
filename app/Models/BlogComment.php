<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlogComment extends Model
{
    use HasFactory;

    protected $table = 'Wo_BlogComments';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'blog_id',
        'text',
        'time',
    ];

    protected $casts = [
        'time' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'blog_id', 'id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(BlogCommentReply::class, 'comm_id', 'id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(BlogReaction::class, 'comment_id', 'id');
    }

    public function getPostedDateAttribute()
    {
        return date('Y-m-d H:i:s', $this->time);
    }
}



