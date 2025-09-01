<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProfileField extends Model
{
    use HasFactory;

    protected $table = 'Wo_ProfileFields';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'type',
        'length',
        'placement',
        'registration_page',
        'profile_page',
        'select_type',
        'active',
    ];

    protected $casts = [
        'registration_page' => 'boolean',
        'profile_page' => 'boolean',
        'active' => 'boolean',
    ];

    public function getTypeLabelAttribute(): string
    {
        $types = [
            'textbox' => 'Textbox',
            'textarea' => 'Textarea',
            'selectbox' => 'Select Box',
        ];

        return $types[$this->type] ?? ucfirst($this->type);
    }

    public function getPlacementLabelAttribute(): string
    {
        $placements = [
            'general' => 'General Settings',
            'profile' => 'Profile Settings',
            'social' => 'Social Links',
            'none' => 'Don\'t Show in Settings',
        ];

        return $placements[$this->placement] ?? ucfirst($this->placement);
    }

    public function getActiveTextAttribute(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }

    public function getRegistrationPageTextAttribute(): string
    {
        return $this->registration_page ? 'Yes' : 'No';
    }

    public function getProfilePageTextAttribute(): string
    {
        return $this->profile_page ? 'Yes' : 'No';
    }
}


