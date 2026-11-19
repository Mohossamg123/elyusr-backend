<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramPageSection extends Model
{
    protected $fillable = [
        'program_id',
        'section_key',
        'title_ar',
        'title_en',
        'subtitle_ar',
        'subtitle_en',
        'content_ar',
        'content_en',
        'settings',
        'is_enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'content_ar' => 'array',
            'content_en' => 'array',
            'settings' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }
}