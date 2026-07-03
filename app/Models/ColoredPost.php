<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ColoredPost extends Model
{
    protected $table = 'Wo_Colored_Posts';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $fillable = [
        'color_1',
        'color_2',
        'text_color',
        'image',
        'time',
    ];

    public function isGradient(): bool
    {
        return !empty($this->color_1) && !empty($this->color_2);
    }

    public function isImageBackground(): bool
    {
        return !empty($this->image);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) {
            return null;
        }

        if (filter_var($this->image, FILTER_VALIDATE_URL)) {
            return $this->image;
        }

        return asset('storage/' . ltrim($this->image, '/'));
    }

    public function deleteStoredImage(): void
    {
        if (empty($this->image) || filter_var($this->image, FILTER_VALIDATE_URL)) {
            return;
        }

        $path = ltrim($this->image, '/');
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
