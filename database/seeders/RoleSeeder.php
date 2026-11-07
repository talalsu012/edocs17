<?php

namespace Database\Seeders;

use App\Enums\Permission as P;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * الأدوار الإدارية المبدئية. تُعدَّل من شاشة «الأدوار والصلاحيات».
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['it_admin', 'مسؤول تقانة المعلومات', 'إدارة الإعدادات والمستخدمين والأدوار والتكليفات والنماذج وبيانات التدريب',
                [P::SettingsManage, P::UsersManage, P::RolesManage, P::AssignmentsManage, P::TemplatesManage, P::TrainingManage]],
            ['assignments_officer', 'مسؤول التكليفات (الموارد البشرية)', 'تسجيل قرارات النقل والتكليف والإنابة وتقارير التسليم',
                [P::AssignmentsManage, P::UsersManage]],
            ['templates_editor', 'محرر النماذج', 'إضافة نماذج الخطابات وتعديلها',
                [P::TemplatesManage]],
            ['reports_viewer', 'مطّلع على التقارير', 'عرض المؤشرات والإحصاءات دون محتوى المراسلات',
                [P::ReportsView]],
        ];

        foreach ($roles as [$name, $label, $desc, $perms]) {
            $role = Role::updateOrCreate(['name' => $name], ['label' => $label, 'description' => $desc, 'is_system' => true]);
            $role->syncPermissions($perms);
        }
    }
}
