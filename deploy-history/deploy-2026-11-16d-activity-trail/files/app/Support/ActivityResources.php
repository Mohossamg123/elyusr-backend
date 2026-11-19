<?php

namespace App\Support;

/**
 * Friendly Arabic names for the API resources staff can change, used by the activity trail.
 * Keys are the first URL segment after /api/v1 (and after `admin/`). Unknown resources fall back to the raw segment.
 */
class ActivityResources
{
    private const NAMES = [
        'donations' => 'تبرع', 'donation-campaigns' => 'حملة تبرع', 'payment-methods' => 'طريقة دفع',
        'programs' => 'برنامج', 'projects' => 'مشروع', 'cases' => 'حالة', 'case-categories' => 'تصنيف حالات',
        'stories' => 'قصة أثر', 'blog-seo' => 'مقال', 'content' => 'محتوى الموقع', 'gallery' => 'معرض الصور',
        'media' => 'مكتبة الوسائط', 'faqs' => 'سؤال شائع', 'faq-categories' => 'تصنيف أسئلة',
        'home-numbers' => 'أرقام الرئيسية', 'achievements' => 'إنجاز', 'axes' => 'محور رسالة',
        'about-us' => 'من نحن', 'homepage-content' => 'الهيرو والرئيسية', 'homepage' => 'الصفحة الرئيسية',
        'site-blocks' => 'بيانات المؤسسة والسيو', 'seasons' => 'موسم', 'users' => 'مستخدم', 'roles' => 'دور وصلاحيات',
        'volunteer-campaigns' => 'حملة تطوع', 'volunteer-applications' => 'طلب تطوع',
        'newsletter' => 'النشرة البريدية', 'mail' => 'مركز البريد', 'backups' => 'النسخ الاحتياطي',
        'misbaha-rooms' => 'غرفة سبحة', 'misbaha' => 'السبحة', 'settings' => 'إعدادات', 'impact' => 'الأثر والشفافية',
        'notifications' => 'إشعارات', 'khabiaa-requests' => 'طلب خبيئة',
    ];

    public static function label(string $segment): string
    {
        return self::NAMES[$segment] ?? $segment;
    }

    /** First meaningful URL segment of an API route (`api/v1/admin/misbaha-rooms/5` → `misbaha-rooms`). */
    public static function segment(string $uri): string
    {
        $parts = array_values(array_filter(explode('/', preg_replace('#^api/(v1/)?#', '', $uri)), fn ($p) => $p !== '' && $p !== 'admin' && $p[0] !== '{'));

        return $parts[0] ?? 'system';
    }
}
