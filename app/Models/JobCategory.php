<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobCategory extends Model
{
    protected $table = 'Wo_Job_Categories';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'lang_key',
        'name',
        'description',
    ];

    // Note: active column might not exist in Wo_JobCategories table

    // Note: Wo_Jobs table might not exist
    // public function jobs(): HasMany
    // {
    //     return $this->hasMany(Job::class, 'category_id', 'id');
    // }

    // Mutators to prevent null values for optional fields
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = $value ?: $this->attributes['description'] ?? '';
    }

    /**
     * Resolve category label from Wo_Langs when name is empty.
     */
    public function getResolvedNameAttribute(): string
    {
        $name = trim((string) ($this->attributes['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        if (!Schema::hasTable('Wo_Langs')) {
            return '';
        }

        $langKey = $this->attributes['lang_key'] ?? null;
        if (empty($langKey)) {
            return '';
        }

        $row = DB::table('Wo_Langs')
            ->when(
                Schema::hasColumn('Wo_Langs', 'type'),
                fn ($q) => $q->whereIn('type', ['job', 'category'])
            )
            ->where(function ($q) use ($langKey) {
                $q->where('id', $langKey)
                  ->orWhere('lang_key', (string) $langKey);
            })
            ->select('english')
            ->first();

        return (string) ($row->english ?? '');
    }
}