<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // البيانات الأساسية
            'faq_category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:faq_categories,id',
            ],

            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',

            'slug' => [
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('projects', 'slug'),
            ],

            'description' => 'nullable|string',
            'description_en' => 'nullable|string',
            'short_description' => 'nullable|string',
            'short_description_en' => 'nullable|string',

            'page_content' => 'nullable|array',
            'content_ar' => 'nullable|string',
            'content_en' => 'nullable|string',

            // المزايا
            'benefits_ar' => 'nullable|array',
            'benefits_ar.*' => 'string|max:1000',

            'benefits_en' => 'nullable|array',
            'benefits_en.*' => 'string|max:1000',

            // المبالغ والترتيب
            // يجب تحديد المبلغ المستهدف بقيمة أكبر من صفر
            'target_amount' => 'required|numeric|min:0.01',

            // collected_amount لا يستقبل من المستخدم
            // يتم تحديثه اعتماداً على التبرعات المدفوعة

            'starting_price' => 'nullable|numeric|min:0',
            'display_order' => 'sometimes|integer|min:0',

            // الصورة
            'cover_image' => $this->hasFile('cover_image')
                ? 'nullable|image|mimes:jpg,jpeg,png,webp,avif|max:5120'
                : 'nullable|string|max:2048',

            // إعدادات الظهور والتبرع
            'is_active' => 'sometimes|boolean',
            'hide_from_audience' => 'sometimes|boolean',
            'donation_available' => 'sometimes|boolean',

            // إعدادات SEO
            'meta_title_ar' => 'nullable|string|max:70',
            'meta_title_en' => 'nullable|string|max:70',

            'meta_description_ar' => 'nullable|string|max:170',
            'meta_description_en' => 'nullable|string|max:170',

            'keywords_ar' => 'nullable|string',
            'keywords_en' => 'nullable|string',

            'canonical_url' => 'nullable|string|max:2048|url',

            'schema_type' => 'nullable|string|max:100',

            'x_card_type' => [
                'nullable',
                Rule::in([
                    'summary',
                    'summary_large_image',
                    'app',
                    'player',
                ]),
            ],

            'noindex' => 'sometimes|boolean',
            'nofollow' => 'sometimes|boolean',

            // الموقع الجغرافي
            'geo_region' => 'nullable|string|max:255',
            'geo_placename' => 'nullable|string|max:255',
            'geo_position' => 'nullable|string|max:100',
            'geo_position_label' => 'nullable|string|max:255',
        ];
    }
}
