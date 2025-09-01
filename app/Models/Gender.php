<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Gender extends Model
{
    use HasFactory;

    protected $table = 'Wo_Gender';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'gender_id',
        'image',
    ];

    public function getNameAttribute(): string
    {
        // Map gender_id to readable names
        $genderNames = [
            'male' => 'Male',
            'female' => 'Female',
            '1802' => 'Other',
            'mal****' => 'Prefer not to say',
        ];

        return $genderNames[$this->gender_id] ?? ucfirst($this->gender_id);
    }

    /**
     * Get all unique gender values from users table
     */
    public static function getGenderOptions(): array
    {
        $genders = DB::table('Wo_Users')
            ->select('gender', DB::raw('COUNT(*) as count'))
            ->whereNotNull('gender')
            ->where('gender', '!=', '')
            ->groupBy('gender')
            ->orderBy('count', 'desc')
            ->get();

        $options = [];
        foreach ($genders as $gender) {
            $options[$gender->gender] = self::getGenderName($gender->gender) . " ({$gender->count} users)";
        }

        return $options;
    }

    /**
     * Get readable name for gender value
     */
    public static function getGenderName(string $genderId): string
    {
        $genderNames = [
            'male' => 'Male',
            'female' => 'Female',
            '1802' => 'Other',
            'mal****' => 'Prefer not to say',
        ];

        return $genderNames[$genderId] ?? ucfirst($genderId);
    }

    public function getImageUrlAttribute(): string
    {
        if ($this->image && file_exists(public_path('upload/photos/' . $this->image))) {
            return asset('upload/photos/' . $this->image);
        }

        return asset('images/placeholders/gender-default.svg');
    }

    public function users()
    {
        return $this->hasMany(User::class, 'gender', 'gender_id');
    }
}
