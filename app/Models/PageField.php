<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageField extends Model
{
    use HasFactory;

    protected $table = 'Wo_Custom_Fields';
    protected $primaryKey = 'id';
    public $timestamps = false;

    protected $fillable = [
        'id',
        'name',
        'description',
        'type',
        'length',
        'placement',
        'required',
        'options',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::addGlobalScope('page', function ($query) {
            $query->where('placement', 'page');
        });
    }

    public function getTypeLabelAttribute(): string
    {
        $types = [
            'textbox' => 'Textbox',
            'textarea' => 'Textarea',
            'selectbox' => 'Select Box',
        ];

        return $types[$this->type] ?? ucfirst($this->type);
    }

    public function getRequiredTextAttribute(): string
    {
        return $this->required === 'on' ? 'Required' : 'Optional';
    }

    public function getActiveTextAttribute(): string
    {
        return $this->active ? 'Active' : 'Inactive';
    }

    public function getOptionsArrayAttribute(): array
    {
        if (empty($this->options)) {
            return [];
        }

        return array_filter(explode("\n", $this->options));
    }
}


