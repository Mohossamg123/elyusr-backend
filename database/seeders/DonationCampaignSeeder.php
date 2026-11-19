<?php

namespace Database\Seeders;

use App\Models\DonationCampaign;
use Illuminate\Database\Seeder;

class DonationCampaignSeeder extends Seeder
{
    public function run(): void
    {
        $campaigns = [
            [
                'name_ar' => 'حملة ستر',
                'name_en' => 'Sitr Campaign',
                'slug' => 'sitr',
                'description_ar' => 'حملة شتوية لتأمين منازل الأسر المستحقة وتوفير الأسرة والمراتب والألحفة والكسوة الشتوية.',
                'description_en' => 'A winter campaign providing home protection, beds, mattresses, quilts and winter clothing for families in need.',
                'sort_order' => 10,
            ],
            [
                'name_ar' => 'حملة مركون',
                'name_en' => 'Markoon Campaign',
                'slug' => 'markoon',
                'description_ar' => 'حملة لجمع الأثاث والأجهزة والملابس والأدوية والكتب وإعادة تقديمها للمستحقين.',
                'description_en' => 'A campaign collecting furniture, appliances, clothes, medicine and books for people in need.',
                'sort_order' => 11,
            ],
            [
                'name_ar' => 'موسم الخير (رمضان)',
                'name_en' => 'Season of Goodness (Ramadan)',
                'slug' => 'ramadan',
                'description_ar' => 'موسم رمضاني لدعم الأسر المستحقة بالغذاء ووجبات الإفطار وغيرها من أوجه الخير.',
                'description_en' => 'A Ramadan season supporting families in need with food, iftar meals and other charitable activities.',
                'sort_order' => 12,
            ],
            [
                'name_ar' => 'حملة أثر',
                'name_en' => 'Athar Campaign',
                'slug' => 'athar',
                'description_ar' => 'حملة مؤسسة اليسر الخيرية: حملة أثر.',
                'description_en' => 'Athar campaign by Al-Yusr Charity Foundation.',
                'sort_order' => 13,
            ],
            [
                'name_ar' => 'عربون خير',
                'name_en' => 'Arboun Kheir',
                'slug' => 'arboun-kheir',
                'description_ar' => 'مبادرة عربون خير من مؤسسة اليسر الخيرية.',
                'description_en' => 'Arboun Kheir initiative by Al-Yusr Charity Foundation.',
                'sort_order' => 14,
            ],
        ];

        foreach ($campaigns as $campaign) {
            DonationCampaign::firstOrCreate(
                ['slug' => $campaign['slug']],
                array_merge($campaign, [
                    'donation_available' => true,
                    'is_active' => true,
                ])
            );
        }
    }
}
