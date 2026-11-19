<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // قراءة إجمالي التبرعات المحمّل مسبقًا من الاستعلام
        $collectedAmount = (float) ($this->collected_amount_sum ?? 0);

        // المبلغ المستهدف للمشروع
        $targetAmount = (float) ($this->target_amount ?? 0);

        // حساب نسبة الإنجاز
        $progressPercentage = $targetAmount > 0
            ? min(100, round(($collectedAmount / $targetAmount) * 100, 2))
            : 0;

        // حساب المبلغ المتبقي
        $remainingAmount = max(0, $targetAmount - $collectedAmount);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'faq_category_id' => $this->faq_category_id,

            'faq_category' => $this->faqCategory
                ? [
                    'id' => $this->faqCategory->id,
                    'slug' => $this->faqCategory->slug,
                    'name_ar' => $this->faqCategory->name_ar,
                ]
                : null,

            'description' => $this->description,
            'description_en' => $this->description_en,
            'short_description' => $this->short_description,
            'short_description_en' => $this->short_description_en,
            'page_content' => $this->page_content,
            'content_ar' => $this->content_ar,
            'content_en' => $this->content_en,
            'benefits_ar' => $this->benefits_ar,
            'benefits_en' => $this->benefits_en,

            'target_amount' => $targetAmount,
            'collected_amount' => $collectedAmount,
            'remaining_amount' => $remainingAmount,
            'progress_percentage' => $progressPercentage,
            'completion_percentage' => $progressPercentage,

            'starting_price' => $this->starting_price,
            'display_order' => $this->display_order,

            'cover_image' => $this->cover_image
                ? (
                    preg_match('#^https?://#', $this->cover_image)
                        ? $this->cover_image
                        : Storage::disk('public')->url(
                            preg_replace(
                                '#^/?storage/#',
                                '',
                                $this->cover_image
                            )
                        )
                )
                : null,

            'is_active' => $this->is_active,
            'hide_from_audience' => $this->hide_from_audience,
            'donation_available' => $this->donation_available,

            'meta_title_ar' => $this->meta_title_ar,
            'meta_title_en' => $this->meta_title_en,
            'meta_description_ar' => $this->meta_description_ar,
            'meta_description_en' => $this->meta_description_en,
            'keywords_ar' => $this->keywords_ar,
            'keywords_en' => $this->keywords_en,
            'canonical_url' => $this->canonical_url,
            'schema_type' => $this->schema_type,
            'x_card_type' => $this->x_card_type,
            'noindex' => $this->noindex,
            'nofollow' => $this->nofollow,

            'geo_region' => $this->geo_region,
            'geo_placename' => $this->geo_placename,
            'geo_position' => $this->geo_position,
            'geo_position_label' => $this->geo_position_label,

            'donation_packages' => $this->whenLoaded(
                'donationPackages',
                fn () => $this->donationPackages
                    ->map(fn ($package) => [
                        'id' => $package->id,
                        'project_id' => $package->project_id,
                        'name_ar' => $package->name_ar,
                        'name_en' => $package->name_en,
                        'amount' => $package->amount,
                        'sort_order' => $package->sort_order,
                        'is_active' => $package->is_active,
                    ])
                    ->values()
            ),

            'cases' => $this->whenLoaded(
                'cases',
                fn () => \App\Support\LinkedCases::visible(
                    $this->cases,
                    $request
                )->map(
                    fn ($case) => [
                        'id' => $case->id,
                        'project_id' => $case->project_id,
                        'category_id' => $case->category_id,
                        'code' => $case->code,
                        'title' => $case->title,

                        'beneficiary_name' => \App\Support\LinkedCases::name(
                            $case,
                            $request
                        ),

                        'target_amount' => $case->target_amount,
                        'minimum_amount' => $case->minimum_amount,
                        'status' => $case->status,
                        'priority' => $case->priority,
                        'governorate' => $case->governorate,

                        'city' => \App\Support\LinkedCases::city(
                            $case,
                            $request
                        ),

                        'cover_image' => \App\Support\LinkedCases::cover(
                            $case->cover_image
                        ),
                    ]
                )->values()
            ),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}