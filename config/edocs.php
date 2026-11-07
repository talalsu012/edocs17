<?php

/*
 * إعدادات نظام المراسلات الإلكترونية — جامعة كسلا
 */
return [

    /*
     * الربط مع قاعدة بيانات الموظفين mang (قراءة مباشرة، لا كتابة).
     * أسماء المجموعات والأعمدة مطابقة لنظام mang الحالي، وتُعدَّل هنا إن تغيّرت.
     */
    'mang' => [
        'enabled' => env('MANG_ENABLED', true),
        'connection' => 'mang',

        // قيم العمود group في employee_settings
        'groups' => [
            'faculty' => 'faculty',
            'department' => 'departments',
            'job_title' => 'job_title',
        ],

        // العمود الذي يربط القسم بكليته في employee_settings
        'parent_column' => env('MANG_SETTINGS_PARENT_COLUMN', 'parent_code'),

        // رمز الجهة في edocs التي تُعلَّق تحتها الكليات المستوردة
        'faculties_parent_unit' => env('MANG_FACULTIES_PARENT', 'أ ش ع'),

        // الأقسام التي لا تتبع كلية تُعلَّق تحت هذه الجهة
        'orphan_departments_parent_unit' => env('MANG_ORPHANS_PARENT', 'م ج'),
    ],

    /*
     * القيم الافتراضية لإعدادات الجامعة؛ تُعدَّل من صفحة «إعدادات الجامعة» وتُحفظ في جدول settings.
     * نوع القيمة هنا يحدد نوعها عند القراءة.
     */
    'settings' => [
        'university_name_ar' => 'جامعة كسلا',
        'university_name_en' => 'Kassala University',
        'system_name' => 'نظام المراسلات الإلكترونية',
        'number_prefix' => 'جك',
        'logo_path' => null,
        'letterhead_address' => 'كسلا — جمهورية السودان',
        'letterhead_phone' => '',
        'letterhead_email' => '',
        'letterhead_website' => '',
        'show_hijri' => true,
        'reminder_days_before' => 2, // تنبيه قبل موعد الرد بعدد أيام
        'max_attachment_mb' => 10,
        'allowed_extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx'],
    ],
];
