<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlogReaction extends Model
{
    use HasFactory;

    protected $table = 'Wo_Blog_Reaction';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'blog_id',
        'comment_id',
        'reply_id',
        'reaction',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class, 'blog_id', 'id');
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(BlogComment::class, 'comment_id', 'id');
    }

    public function reply(): BelongsTo
    {
        return $this->belongsTo(BlogCommentReply::class, 'reply_id', 'id');
    }
}



