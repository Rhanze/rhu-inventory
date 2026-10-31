<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Disease extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category',
        'description',
        'is_reportable',
        'is_active',
    ];

    protected $casts = [
        'is_reportable' => 'boolean',
        'is_active'     => 'boolean',
    ];

    public function reports()
    {
        return $this->hasMany(DiseaseReport::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}