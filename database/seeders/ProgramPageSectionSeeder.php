<?php

namespace Database\Seeders;

use App\Models\Program;
use App\Models\ProgramPageSection;
use Illuminate\Database\Seeder;

class ProgramPageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [
                'section_key' => 'benefits',
                'title_ar' => 'مزايا البرنامج',
                'title_en' => 'Program Benefits',
                'subtitle_ar' => 'ليه البرنامج ده',
                'subtitle_en' => 'Why this program?',
                'sort_order' => 1,
            ],
            [
                'section_key' => 'how_it_works',
                'title_ar' => 'كيف يعمل البرنامج؟',
                'title_en' => 'How It Works',
                'subtitle_ar' => 'خطوات تنفيذ البرنامج',
                'subtitle_en' => 'Program steps',
                'sort_order' => 2,
            ],
            [
                'section_key' => 'details',
                'title_ar' => 'كل ما تحتاج معرفته',
                'title_en' => 'Everything You Need to Know',
                'sort_order' => 3,
            ],
            [
                'section_key' => 'impact',
                'title_ar' => 'الأثر',
                'title_en' => 'Impact',
                'subtitle_ar' => 'أثر مساهمتك',
                'subtitle_en' => 'Your contribution impact',
                'sort_order' => 4,
            ],
            [
                'section_key' => 'donor_testimonials',
                'title_ar' => 'آراء المتبرعين',
                'title_en' => 'Donor Testimonials',
                'sort_order' => 5,
            ],
            [
                'section_key' => 'faq',
                'title_ar' => 'أسئلة شائعة',
                'title_en' => 'Frequently Asked Questions',
                'sort_order' => 6,
            ],
            [
                'section_key' => 'related_programs',
                'title_ar' => 'برامج مشابهة',
                'title_en' => 'Related Programs',
                'sort_order' => 7,
            ],
        ];

        $programs = Program::all();

        if ($programs->isEmpty()) {
            $this->command?->warn('لا توجد برامج لإضافة الأقسام إليها.');
            return;
        }

        foreach ($programs as $program) {
            foreach ($sections as $section) {
                ProgramPageSection::firstOrCreate(
                    [
                        'program_id' => $program->id,
                        'section_key' => $section['section_key'],
                    ],
                    array_merge($section, [
                        'is_enabled' => true,
                        'content_ar' => [],
                        'content_en' => [],
                        'settings' => [],
                    ])
                );
            }
        }

        $this->command?->info(
            "تم تجهيز أقسام الصفحات لعدد {$programs->count()} برنامج."
        );
    }
}