<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Season extends Model
{
    protected $fillable = [
        'slug',
        'badge_ar',
        'badge_en',
        'donate_url',
        'pricing',
        'items',
        'meta_title_ar',
        'meta_title_en',
        'meta_description_ar',
        'meta_description_en',
        'name_ar',
        'name_en',
        'period_ar',
        'period_en',
        'tagline_ar',
        'tagline_en',
        'description_ar',
        'description_en',
        'is_active',
        'donation_available',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'donation_available' => 'boolean',
        'sort_order' => 'integer',
        'pricing' => 'array',
        'items' => 'array',
    ];

    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(
            Program::class,
            'program_season'
        );
    }
}