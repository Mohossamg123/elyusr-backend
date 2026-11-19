<?php

namespace App\Models;
use App\Models\Donation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $with = ['faqCategory'];

    protected $fillable = [
        'faq_category_id',
        'name',
        'name_en',
        'slug',
        'page_content',
        'description',
        'description_ar',
        'description_en',
        'short_description',
        'short_description_en',
        'content_ar',
        'content_en',
        'benefits_ar',
        'benefits_en',

        'target_amount',
        'collected_amount',
        'starting_price',
        'display_order',
        'cover_image',

        'is_active',
        'hide_from_audience',
        'donation_available',

        'meta_title_ar',
        'meta_title_en',
        'meta_description_ar',
        'meta_description_en',
        'keywords_ar',
        'keywords_en',
        'canonical_url',
        'noindex',
        'nofollow',
        'schema_type',
        'x_card_type',

        'geo_region',
        'geo_placename',
        'geo_position',
        'geo_position_label',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'collected_amount' => 'decimal:2',
        'page_content' => 'array',
        'starting_price' => 'decimal:2',
        'is_active' => 'boolean',
        'hide_from_audience' => 'boolean',
        'donation_available' => 'boolean',
        'noindex' => 'boolean',
        'nofollow' => 'boolean',
        'display_order' => 'integer',
        'benefits_ar' => 'array',
        'benefits_en' => 'array',
    ];

    public function donationPackages(): HasMany
    {
        return $this->hasMany(
            ProgramDonationPackage::class,
            'project_id'
        );
    }

    public function cases(): HasMany
    {
        return $this->hasMany(
            CaseModel::class,
            'project_id'
        );
    }
    public function donations(): HasMany
{
    return $this->hasMany(Donation::class, 'project_id');
}

    public function faqCategory()
    {
        return $this->belongsTo(
            FaqCategory::class,
            'faq_category_id'
        );
    }
}