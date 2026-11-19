<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $project = $this->route('project');

        $projectId = is_object($project)
            ? $project->id
            : $project;

        return [
            // البيانات الأساسية
            'faq_category_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:faq_categories,id',
            ],

            'name' => 'sometimes|required|string|max:255',
            'name_en' => 'sometimes|nullable|string|max:255',

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('projects', 'slug')->ignore($projectId),
            ],

            'description' => 'sometimes|nullable|string',
            'description_en' => 'sometimes|nullable|string',

            'short_description' => 'sometimes|nullable|string',
            'short_description_en' => 'sometimes|nullable|string',

            'page_content' => 'sometimes|nullable|array',

            'content_ar' => 'sometimes|nullable|string',
            'content_en' => 'sometimes|nullable|string',

            // المزايا
            'benefits_ar' => 'sometimes|nullable|array',
            'benefits_ar.*' => 'string|max:1000',

            'benefits_en' => 'sometimes|nullable|array',
            'benefits_en.*' => 'string|max:1000',

            // المبالغ والترتيب
            // يسمح بالتعديل دون إرسال المبلغ في كل مرة
            'target_amount' => 'sometimes|required|numeric|min:0.01',

            // collected_amount لا يستقبل من المستخدم
            // يتم تحديثه اعتماداً على التبرعات المدفوعة

            'starting_price' => 'sometimes|nullable|numeric|min:0',

            'display_order' => 'sometimes|integer|min:0',

            // الصورة
            'cover_image' => $this->hasFile('cover_image')
                ? 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp,avif|max:5120'
                : 'sometimes|nullable|string|max:2048',

            // إعدادات الظهور والتبرع
            'is_active' => 'sometimes|boolean',
            'hide_from_audience' => 'sometimes|boolean',
            'donation_available' => 'sometimes|boolean',

            // إعدادات SEO
            'meta_title_ar' => 'sometimes|nullable|string|max:70',
            'meta_title_en' => 'sometimes|nullable|string|max:70',

            'meta_description_ar' => 'sometimes|nullable|string|max:170',
            'meta_description_en' => 'sometimes|nullable|string|max:170',

            'keywords_ar' => 'sometimes|nullable|string',
            'keywords_en' => 'sometimes|nullable|string',

            'canonical_url' => 'sometimes|nullable|string|max:2048|url',

            'schema_type' => 'sometimes|nullable|string|max:100',

            'x_card_type' => [
                'sometimes',
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
            'geo_region' => 'sometimes|nullable|string|max:255',
            'geo_placename' => 'sometimes|nullable|string|max:255',
            'geo_position' => 'sometimes|nullable|string|max:100',
            'geo_position_label' => 'sometimes|nullable|string|max:255',
        ];
    }
}
