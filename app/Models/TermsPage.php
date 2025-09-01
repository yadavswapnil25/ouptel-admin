<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TermsPage extends Model
{
    use HasFactory;

    protected $table = 'Wo_Langs';
    
    protected $fillable = [
        'lang_key',
        'type',
        'english',
        'arabic',
    ];

    public $timestamps = false;

    // Constants for page types
    const TERMS_OF_USE = 'terms_of_use_page';
    const PRIVACY_POLICY = 'privacy_policy_page';
    const ABOUT = 'about_page';
    const REFUND_TERMS = 'refund_terms_page';

    /**
     * Get all terms pages
     */
    public static function getTermsPages()
    {
        return static::whereIn('lang_key', [
            self::TERMS_OF_USE,
            self::PRIVACY_POLICY,
            self::ABOUT,
            self::REFUND_TERMS,
        ])->get();
    }

    /**
     * Get page by type
     */
    public static function getByType($type)
    {
        return static::where('lang_key', $type)->first();
    }

    /**
     * Get page display name
     */
    public function getDisplayNameAttribute()
    {
        return match($this->lang_key) {
            self::TERMS_OF_USE => 'Terms of Use',
            self::PRIVACY_POLICY => 'Privacy Policy',
            self::ABOUT => 'About',
            self::REFUND_TERMS => 'Refund Terms',
            default => ucfirst(str_replace('_', ' ', $this->lang_key)),
        };
    }

    /**
     * Get page description
     */
    public function getDescriptionAttribute()
    {
        return match($this->lang_key) {
            self::TERMS_OF_USE => 'Terms and conditions for using the platform',
            self::PRIVACY_POLICY => 'Privacy policy and data protection information',
            self::ABOUT => 'About us and company information',
            self::REFUND_TERMS => 'Refund policy and terms',
            default => 'Page content',
        };
    }

    /**
     * Get English content
     */
    public function getEnglishContentAttribute()
    {
        return $this->english ?? '';
    }

    /**
     * Get Arabic content
     */
    public function getArabicContentAttribute()
    {
        return $this->arabic ?? '';
    }
}

