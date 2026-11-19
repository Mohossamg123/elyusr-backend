<?php

namespace App\Http\Resources;

use App\Support\Viewer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // تحديد هل المستخدم موظف أو مسؤول.
        $staff = Viewer::isStaff($request);

        // قراءة إجمالي التبرعات المحسوب مسبقًا بواسطة withSum.
        // كده مش بنعمل استعلام جديد لكل حالة.
        $collected = (float) ($this->collected_amount_sum ?? 0);

        $targetAmount = (float) $this->target_amount;

        $data = [
            'id' => $this->id,
            'axis_id' => $this->axis_id,
            'code' => $this->code,
            'title' => $this->title,

            'category' => $this->category,

            'category_data' => $this->when(
                $this->relationLoaded('categoryRelation') && $this->categoryRelation,
                fn () => [
                    'id' => $this->categoryRelation->id,
                    'name_ar' => $this->categoryRelation->name_ar,
                    'name_en' => $this->categoryRelation->name_en,
                    'slug' => $this->categoryRelation->slug,
                ]
            ),

            'program' => $this->when(
                $this->relationLoaded('program') && $this->program,
                fn () => [
                    'id' => $this->program->id,
                    'name' => $this->program->name,
                    'name_ar' => $this->program->name_ar,
                    'name_en' => $this->program->name_en,
                    'slug' => $this->program->slug,
                ]
            ),

            'project' => $this->when(
                $this->relationLoaded('project') && $this->project,
                fn () => [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'slug' => $this->project->slug,
                ]
            ),

            'donation_campaign' => $this->when(
                $this->relationLoaded('donationCampaign')
                    && $this->donationCampaign,
                fn () => [
                    'id' => $this->donationCampaign->id,
                    'name_ar' => $this->donationCampaign->name_ar,
                    'name_en' => $this->donationCampaign->name_en,
                    'slug' => $this->donationCampaign->slug,
                ]
            ),

            // إجمالي التبرعات المدفوعة.
            'collected_amount' => $collected,

            // المبلغ المتبقي للوصول إلى الهدف.
            'remaining_amount' => max(0, $targetAmount - $collected),

            // نسبة اكتمال التبرعات.
            'completion_percentage' => $targetAmount > 0
                ? min(100, round(($collected / $targetAmount) * 100, 2))
                : 0,

            'is_urgent' => $this->priority === 'high',

            'beneficiary_name' => $staff
                ? $this->beneficiary_name
                : Viewer::maskName($this->beneficiary_name),

            'age' => $staff ? $this->age : null,
            'governorate' => $this->governorate,
            'city' => $staff ? $this->city : null,
            'target_amount' => $this->target_amount,
            'deadline' => $this->deadline,
            'priority' => $this->priority,

            'cover_image' => $this->cover_image
                ? (
                    preg_match('#^https?://#', $this->cover_image)
                        ? $this->cover_image
                        : Storage::disk('public')->url(
                            preg_replace('#^/?storage/#', '', $this->cover_image)
                        )
                )
                : null,

            'description' => $this->description,
            'status' => $this->status,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        return array_filter(
            $data,
            static fn ($value) => $value !== null
        );
    }
}