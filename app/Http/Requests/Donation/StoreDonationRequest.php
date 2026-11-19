<?php

namespace App\Http\Requests\Donation;

use App\Models\Program;
use App\Models\Project;
use App\Models\DonationCampaign;
use App\Models\CaseModel;
use App\Models\ProgramDonationPackage;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $inKind = $this->boolean('is_in_kind');

        $this->merge([
            'is_in_kind' => $inKind,
            'amount' => $inKind ? 0 : $this->input('amount'),
        ]);
    }

    public function rules(): array
    {
        return [
    
            'name' => [
                'nullable',
                'string',
                'max:255',
            ],

            'email' => [
                'nullable',
                'email',
                'max:255',
            ],

            'phone' => [
                Rule::requiredIf(fn () => ! $this->user('sanctum')),
                'nullable',
                'string',
                'regex:/^\+?[0-9٠-٩\s\-()]{8,20}$/u',
            ],

            'address' => [
                'nullable',
                'string',
                'max:500',
            ],

            'program_id' => [
                'nullable',
                'integer',
                'exists:programs,id',
            ],

            'project_id' => [
                'nullable',
                'integer',
                'exists:projects,id',
            ],

            'donation_campaign_id' => [
                'nullable',
                'integer',
                'exists:donation_campaigns,id',
            ],

            'case_id' => [
                'nullable',
                'integer',
                'exists:case_models,id',
            ],

            'donation_package_id' => [
                'nullable',
                'integer',
                'exists:program_donation_packages,id',
            ],

            'payment_method_id' => [
                'required',
                'integer',
                'exists:payment_methods,id',
            ],

            'payment_reference' => [
                'nullable',
                'string',
                'max:255',
            ],

            'payment_receipt' => [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],

            'payment_details' => [
                'nullable',
                'array',
            ],

            'amount' => [
                'required',
                'numeric',
                $this->boolean('is_in_kind') ? 'min:0' : 'min:1',
                'max:1000000',
            ],

            // In-kind donation (furniture, clothes...): amount is 0 and the
            // item type is required. Must go through the pickup agent.
            'is_in_kind' => [
                'boolean',
            ],

            'in_kind_category' => [
                Rule::requiredIf(fn () => $this->boolean('is_in_kind')),
                'nullable',
                'string',
                'max:100',
            ],

            'in_kind_description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'pickup_governorate' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pickup_city' => [
                'nullable',
                'string',
                'max:255',
            ],

            'pickup_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'pickup_preferred_time' => [
                'nullable',
                'string',
                'max:255',
            ],

            'payment_method' => [
                'nullable',
                'in:cash,visa,bank',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب عشان نقدر نأكد تبرعك.',
            'phone.regex' => 'رقم الهاتف غير صحيح.',
            'email.email' => 'البريد الإلكتروني غير صحيح.',
            'amount.required' => 'مبلغ التبرع مطلوب.',
            'amount.min' => 'مبلغ التبرع يجب ألا يقل عن 1 جنيه.',
            'in_kind_category.required' => 'اختر نوع التبرع العيني.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            $method = \App\Models\PaymentMethod::find(
                $this->input('payment_method_id')
            );
            $isPickup = $method && $method->type === 'cash_pickup';

            if ($this->boolean('is_in_kind') && $method && ! $isPickup) {
                $validator->errors()->add(
                    'payment_method_id',
                    'التبرع العيني يتم عن طريق مندوب الاستلام فقط.'
                );
            }

            if ($isPickup) {
                $requiredPickup = [
                    'pickup_governorate' => 'المحافظة',
                    'pickup_city' => 'المدينة / المنطقة',
                    'pickup_address' => 'العنوان التفصيلي',
                ];

                foreach ($requiredPickup as $field => $label) {
                    if (! $this->filled($field)) {
                        $validator->errors()->add(
                            $field,
                            "{$label} مطلوب لطلب المندوب."
                        );
                    }
                }
            }

            $topLevelTargets = collect([
                'program_id' => $this->input('program_id'),
                'project_id' => $this->input('project_id'),
                'donation_campaign_id' => $this->input(
                    'donation_campaign_id'
                ),
            ])->filter(
                fn ($value) => filled($value)
            );

            $hasCase = $this->filled('case_id');

            if (
                $topLevelTargets->isEmpty() &&
                !$hasCase
            ) {
                $validator->errors()->add(
                    'donation_target',
                    'يجب اختيار حالة أو برنامج أو مشروع أو حملة تبرع.'
                );

                return;
            }

            if ($topLevelTargets->count() > 1) {
                $validator->errors()->add(
                    'donation_target',
                    'يجب اختيار برنامج أو مشروع أو حملة تبرع واحدة فقط.'
                );

                return;
            }

            if ($this->filled('program_id')) {
                $program = Program::with('seasons')
                    ->find($this->input('program_id'));

                if (
                    !$program ||
                    !$program->is_active ||
                    !$program->donation_available
                ) {
                    $validator->errors()->add(
                        'program_id',
                        'هذا البرنامج غير متاح للتبرع حاليًا.'
                    );
                } else {
                    if (
                        $program->seasons->isNotEmpty() &&
                        !$program->seasons->contains(
                            fn ($season) =>
                                $season->is_active &&
                                $season->donation_available
                        )
                    ) {
                        $validator->errors()->add(
                            'program_id',
                            'موسم هذا البرنامج غير متاح للتبرع حاليًا.'
                        );
                    }
                }
            }

            if ($this->filled('project_id')) {
                $project = Project::find(
                    $this->input('project_id')
                );

                if (!$project || !$project->is_active) {
                    $validator->errors()->add(
                        'project_id',
                        'هذا المشروع غير متاح للتبرع حاليًا.'
                    );
                } elseif (
                    (float) $project->target_amount > 0 && 
                    (float) $project->collected_amount >=
                        (float) $project->target_amount
                ) {
                    $validator->errors()->add(
                        'project_id',
                        'اكتمل المبلغ المستهدف لهذا المشروع ولا يستقبل تبرعات جديدة.'
                    );
                } elseif (
                    (float) $project->target_amount > 0 && 
                    (float) $this->input('amount', 0) >
                        (
                            (float) $project->target_amount -
                            (float) $project->collected_amount
                        )
                ) {
                    $validator->errors()->add(
                        'amount',
                        'مبلغ التبرع أكبر من المبلغ المتبقي لتحقيق هدف المشروع.'
                    );
                }
            }

if ($this->filled('donation_campaign_id')) {
    $campaign = DonationCampaign::find(
        $this->input('donation_campaign_id')
    );

    if (!$campaign) {
        $validator->errors()->add(
            'donation_campaign_id',
            'حملة التبرع غير موجودة.'
        );
    } elseif (
        !$campaign->is_active ||
        !$campaign->donation_available
    ) {
        $validator->errors()->add(
            'donation_campaign_id',
            'حملة التبرع غير متاحة حاليًا.'
        );
    } else {
        // إجمالي التبرعات المدفوعة للحملة.
        $collectedAmount = (float) $campaign->donations()
            ->where('status', 'paid')
            ->sum('amount');

        $targetAmount = (float) $campaign->target_amount;

        if (
            $targetAmount > 0 &&
            $collectedAmount >= $targetAmount
        ) {
            $validator->errors()->add(
                'donation_campaign_id',
                'اكتمل المبلغ المستهدف لهذه الحملة.'
            );
        } elseif (
            $targetAmount > 0 &&
            (float) $this->input('amount', 0) >
                ($targetAmount - $collectedAmount)
        ) {
            $validator->errors()->add(
                'amount',
                'مبلغ التبرع أكبر من المبلغ المتبقي للحملة.'
            );
        }
    }
}
            if ($hasCase) {
                $case = CaseModel::find(
                    $this->input('case_id')
                );

                if (!$case) {
                    $validator->errors()->add(
                        'case_id',
                        'الحالة المختارة غير موجودة.'
                    );

                    return;
                }

                if (
                    $this->filled('program_id') &&
                    (int) $case->program_id !==
                        (int) $this->input('program_id')
                ) {
                    $validator->errors()->add(
                        'case_id',
                        'الحالة المختارة لا تتبع البرنامج المحدد.'
                    );
                }

                if (
                    $this->filled('project_id') &&
                    (int) $case->project_id !==
                        (int) $this->input('project_id')
                ) {
                    $validator->errors()->add(
                        'case_id',
                        'الحالة المختارة لا تتبع المشروع المحدد.'
                    );
                }

                if (
                    $this->filled('donation_campaign_id') &&
                    (int) $case->donation_campaign_id !==
                        (int) $this->input('donation_campaign_id')
                ) {
                    $validator->errors()->add(
                        'case_id',
                        'الحالة المختارة لا تتبع حملة التبرع المحددة.'
                    );
                }

                if ($case->status !== 'approved') {
                    $validator->errors()->add(
                        'case_id',
                        'هذه الحالة غير متاحة للتبرع حاليًا.'
                    );
                }

                $collectedAmount = (float) $case->donations()
                    ->where('status', 'paid')
                    ->sum('amount');

                $targetAmount = (float) $case->target_amount;

                if (
                    $targetAmount > 0 &&
                    $collectedAmount >= $targetAmount
                ) {
                    $validator->errors()->add(
                        'case_id',
                        'اكتمل المبلغ المستهدف لهذه الحالة.'
                    );
                } elseif (
                    $targetAmount > 0 &&
                    (float) $this->input('amount', 0) >
                        ($targetAmount - $collectedAmount)
                ) {
                    $validator->errors()->add(
                        'amount',
                        'مبلغ التبرع أكبر من المبلغ المتبقي للحالة.'
                    );
                }
            }

            if ($this->filled('donation_package_id')) {
                $package = ProgramDonationPackage::find(
                    $this->input('donation_package_id')
                );

                if (!$package) {
                    $validator->errors()->add(
                        'donation_package_id',
                        'الباقة المختارة غير موجودة.'
                    );

                    return;
                }

                if (
                    $this->filled('program_id') &&
                    (int) $package->program_id !==
                        (int) $this->input('program_id')
                ) {
                    $validator->errors()->add(
                        'donation_package_id',
                        'الباقة المختارة لا تتبع البرنامج المحدد.'
                    );
                }

                if (
                    $this->filled('project_id') &&
                    (int) $package->project_id !==
                        (int) $this->input('project_id')
                ) {
                    $validator->errors()->add(
                        'donation_package_id',
                        'الباقة المختارة لا تتبع المشروع المحدد.'
                    );
                }

                if (
                    $this->filled('donation_campaign_id') &&
                    (int) $package->donation_campaign_id !==
                        (int) $this->input('donation_campaign_id')
                ) {
                    $validator->errors()->add(
                        'donation_package_id',
                        'الباقة المختارة لا تتبع حملة التبرع المحددة.'
                    );
                }

                if (
                    $package->amount !== null &&
                    (float) $this->input('amount') !==
                        (float) $package->amount
                ) {
                    $validator->errors()->add(
                        'amount',
                        'مبلغ التبرع يجب أن يطابق مبلغ الباقة المختارة.'
                    );
                }

                if (!$package->is_active) {
                    $validator->errors()->add(
                        'donation_package_id',
                        'الباقة المختارة غير متاحة حاليًا.'
                    );
                }
            }
        });
    }
}