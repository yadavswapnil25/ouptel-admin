<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobCategory extends Model
{
    protected $table = 'Wo_Job_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'lang_key',
    ];

    // Relationships
    public function jobs()
    {
        return $this->hasMany(Job::class, 'category', 'id');
    }

    // Accessors
    public function getNameAttribute(): string
    {
        // The lang_key is actually an ID that references Wo_Langs table
        // We need to fetch the English translation from the language table
        $langKey = $this->lang_key;
        
        if (!$langKey) {
            return 'Uncategorized';
        }
        
        // Try to get the English translation from Wo_Langs table
        $translation = \Illuminate\Support\Facades\DB::table('Wo_Langs')
            ->where('id', $langKey)
            ->where('type', 'category')
            ->first();
            
        if ($translation && !empty($translation->english)) {
            return $translation->english;
        }
        
        // Fallback to the lang_key if no translation found
        return $langKey;
    }
}
