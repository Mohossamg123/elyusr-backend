<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProgramListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // تجهيز صورة البرنامج.
        $image = $this->image
            ? (
                preg_match('#^https?://#', $this->image)
                    ? $this->image
                    : Storage::disk('public')->url(
                        preg_replace('#^/?storage/#', '', $this->image)
                    )
            )
            : null;

        // تجهيز صورة القسم العلوي.
        $topSectionImage = data_get($this->top_section, 'image');

        if (
            $topSectionImage &&
            !str_starts_with($topSectionImage, 'http')
        ) {
            $topSectionImage = Storage::disk('public')->url(
                preg_replace('#^/?storage/#', '', $topSectionImage)
            );
        }

        // تجهيز صورة Hero.
        $heroImage = $this->hero_image
            ? (
                str_starts_with($this->hero_image, 'http')
                    ? $this->hero_image
                    : Storage::disk('public')->url(
                        preg_replace('#^/?storage/#', '', $this->hero_image)
                    )
            )
            : null;

        return [
            // بيانات تعريف البرنامج.
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'name_ar' => $this->name_ar ?? $this->name,
            'name_en' => $this->name_en,
            'title_ar' => $this->name_ar ?? $this->name,
            'title_en' => $this->name_en,

            // بيانات الكارت الظاهر في قائمة البرامج.
            'hero_h1_ar' => $this->hero_h1_ar,
            'hero_h1_en' => $this->hero_h1_en,
            'hero_h3_ar' => $this->hero_h3_ar,
            'hero_h3_en' => $this->hero_h3_en,
            'hero_image' => $heroImage,

            // بيانات القسم العلوي.
            'top_section' => [
                'badge_ar' => data_get(
                    $this->top_section,
                    'badge_ar',
                    'برنامج معتمد'
                ),
                'title_ar' => $this->name_ar ?? $this->name,
                'title_en' => $this->name_en,
                'subtitle_ar' => $this->tagline_ar,
                'subtitle_en' => $this->tagline_en,
                'description_ar' => $this->description_ar ?? $this->description,
                'description_en' => $this->description_en,
                'image' => $topSectionImage,
                'primary_button_ar' => data_get(
                    $this->top_section,
                    'primary_button_ar',
                    'تبرع لهذا البرنامج'
                ),
                'whatsapp_button_ar' => data_get(
                    $this->top_section,
                    'whatsapp_button_ar',
                    'WhatsApp'
                ),
                'minimum_amount' => $this->minimum_amount,
                'currency_ar' => data_get(
                    $this->top_section,
                    'currency_ar',
                    'ج'
                ),
                'donation_available' => $this->donation_available,
            ],

            // النصوص والمبالغ.
            'period_ar' => $this->period_ar,
            'period_en' => $this->period_en,
            'tagline_ar' => $this->tagline_ar,
            'tagline_en' => $this->tagline_en,
            'description' => $this->description,
            'description_ar' => $this->description_ar ?? $this->description,
            'description_en' => $this->description_en,

            'image' => $image,
            'donation_available' => $this->donation_available,
            'target_amount' => $this->target_amount,
            'minimum_amount' => $this->minimum_amount,

            // بيانات المحور.
            'axis_id' => $this->axis_id,

            'axis' => $this->when(
                $this->relationLoaded('axis') && $this->axis,
                fn () => [
                    'id' => $this->axis->id,
                    'slug' => $this->axis->slug,
                    'name_ar' => $this->axis->name_ar,
                    'name_en' => $this->axis->name_en,
                ]
            ),

            // بيانات المواسم.
            'seasons' => $this->when(
                $this->relationLoaded('seasons'),
                fn () => $this->seasons->map(fn ($season) => array_filter([
                    'id' => $season->id,
                    'name_ar' => $season->name_ar,
                    'name_en' => $season->name_en,
                    'period_ar' => $season->period_ar,
                    'period_en' => $season->period_en,
                    'tagline_ar' => $season->tagline_ar,
                    'tagline_en' => $season->tagline_en,
                    'is_active' => $season->is_active,
                    'donation_available' => $season->donation_available,
                ], static fn ($value) => $value !== null))->values()
            ),

            // حالة البرنامج وترتيبه.
            'status' => $this->status,
            'is_active' => $this->is_active,
            'hide_from_audience' => $this->hide_from_audience,
            'sort_order' => $this->sort_order,
        ];
    }
}
