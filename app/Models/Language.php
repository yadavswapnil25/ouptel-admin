<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $table = 'Wo_UserLanguages';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'lang_key',
    ];

    public function getNameAttribute(): string
    {
        // Extract language name from lang_key (e.g., "English_en" -> "English")
        $parts = explode('_', $this->lang_key);
        return ucfirst($parts[0] ?? $this->lang_key);
    }

    public function getCodeAttribute(): string
    {
        // Extract language code from lang_key (e.g., "English_en" -> "en")
        $parts = explode('_', $this->lang_key);
        return $parts[1] ?? '';
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name . ' (' . strtoupper($this->code) . ')';
    }


}


