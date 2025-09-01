<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LanguageKey extends Model
{
    use HasFactory;

    protected $table = 'Wo_Langs';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'lang_key',
        'type',
        'english',
        'arabic',
        'french',
        'german',
        'italian',
        'russian',
        'spanish',
    ];

    public function getAvailableLanguagesAttribute(): array
    {
        return [
            'english' => 'English',
            'arabic' => 'Arabic',
            'french' => 'French',
            'german' => 'German',
            'italian' => 'Italian',
            'russian' => 'Russian',
            'spanish' => 'Spanish',
        ];
    }

    public function getTranslationForLanguage(string $language): ?string
    {
        return $this->{$language} ?? null;
    }

    public function setTranslationForLanguage(string $language, string $value): void
    {
        if (in_array($language, array_keys($this->available_languages))) {
            $this->{$language} = $value;
        }
    }

    public function getEnglishValueAttribute(): string
    {
        return $this->english ?? '';
    }

    public function getArabicValueAttribute(): string
    {
        return $this->arabic ?? '';
    }

    public function getFrenchValueAttribute(): string
    {
        return $this->french ?? '';
    }

    public function getGermanValueAttribute(): string
    {
        return $this->german ?? '';
    }

    public function getItalianValueAttribute(): string
    {
        return $this->italian ?? '';
    }

    public function getRussianValueAttribute(): string
    {
        return $this->russian ?? '';
    }

    public function getSpanishValueAttribute(): string
    {
        return $this->spanish ?? '';
    }
}


