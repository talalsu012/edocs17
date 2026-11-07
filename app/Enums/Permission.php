<?php

namespace App\Enums;

/**
 * الصلاحيات الإدارية. صلاحيات المراسلات (الإرسال باسم جهة والرؤية الهرمية) تأتي من التكليفات،
 * ولا تمنح أيٌّ من هذه الصلاحيات الاطلاع على محتوى المراسلات.
 */
enum Permission: string
{
    case SettingsManage = 'settings.manage';
    case RolesManage = 'roles.manage';
    case UsersManage = 'users.manage';
    case AssignmentsManage = 'assignments.manage';
    case TemplatesManage = 'templates.manage';
    case ReportsView = 'reports.view';
    case TrainingManage = 'training.manage';

    public function label(): string
    {
        return match ($this) {
            self::SettingsManage => 'إدارة إعدادات الجامعة',
            self::RolesManage => 'إدارة الأدوار والصلاحيات',
            self::UsersManage => 'إدارة المستخدمين (تفعيل، أدوار، كلمات المرور)',
            self::AssignmentsManage => 'إدارة التكليفات والتنقلات',
            self::TemplatesManage => 'إدارة نماذج الخطابات',
            self::ReportsView => 'عرض التقارير والمؤشرات الإحصائية',
            self::TrainingManage => 'إدارة بيانات التدريب',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::SettingsManage, self::TrainingManage => 'النظام',
            self::RolesManage, self::UsersManage => 'المستخدمون',
            self::AssignmentsManage => 'الهيكل',
            self::TemplatesManage => 'النماذج',
            self::ReportsView => 'التقارير',
        };
    }
}
