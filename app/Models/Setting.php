<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings'; // matches Wo_Config structure
    
    protected $fillable = [
        'name',
        'value',
    ];

    protected $casts = [
        'value' => 'string',
    ];

    /**
     * Get a setting value by name (matches WoWonder structure)
     */
    public static function get(string $name, $default = null)
    {
        try {
            $setting = static::where('name', $name)->first();
            return $setting ? $setting->value : $default;
        } catch (\Exception $e) {
            // If settings table doesn't exist, return default value
            return $default;
        }
    }

    /**
     * Set a setting value (matches WoWonder structure)
     */
    public static function set(string $name, $value): void
    {
        try {
            static::updateOrCreate(
                ['name' => $name],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : $value]
            );
        } catch (\Exception $e) {
            // If settings table doesn't exist, do nothing
            // This allows the UI to work without the table
        }
    }

    /**
     * Get all settings as name-value pairs
     */
    public static function getAll()
    {
        try {
            return static::all()->keyBy('name');
        } catch (\Exception $e) {
            // If settings table doesn't exist, return empty collection
            return collect();
        }
    }
}