<?php

namespace Database\Seeders;

use App\Enums\AssignmentKind;
use App\Models\Role;
use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * حسابات تجريبية: مدير النظام، ومستخدمو جهات رئيسية، وموظفون عاديون.
 * الحسابات الفعلية للموظفين تُنشأ من mang: php artisan mang:import-users
 * كلمة المرور لكل الحسابات: password — تُغيَّر قبل التشغيل الفعلي.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $unit = fn (string $code) => Unit::where('code', $code)->firstOrFail();

        // مدير النظام (إدارة تقانة المعلومات) — لا يرى محتوى المراسلات افتراضيًا
        User::create([
            'name' => 'مدير النظام',
            'employee_no' => 'IT-001',
            'email' => 'admin@edocs.test',
            'password' => 'password',
            'home_unit_id' => $unit('ت م')->id,
            'job_title' => 'مدير النظام',
            'is_admin' => true,
        ]);

        // [البريد, الاسم, الرقم الوظيفي, رمز الجهة المكلّف بها]
        $holders = [
            ['president@edocs.test', 'مدير الجامعة', 'KU-0001', 'م ج'],
            ['vp@edocs.test', 'نائب المدير', 'KU-0002', 'ن م'],
            ['academic@edocs.test', 'أمين الشؤون العلمية', 'KU-0003', 'أ ش ع'],
            ['registrar@edocs.test', 'وكيل الجامعة', 'KU-0004', 'و ج'],
            ['hr@edocs.test', 'مدير الموارد البشرية', 'KU-0010', 'م ب ش'],
            ['finance@edocs.test', 'مدير الشؤون المالية', 'KU-0011', 'ش م'],
            ['it@edocs.test', 'مدير تقانة المعلومات', 'KU-0012', 'ت م'],
            ['eng.dean@edocs.test', 'عميد كلية الهندسة', 'KU-0100', 'ك ه'],
            ['eng.ee@edocs.test', 'رئيس قسم الهندسة الكهربائية', 'KU-0101', 'ه ك'],
            ['eng.civil@edocs.test', 'رئيس قسم الهندسة المدنية', 'KU-0102', 'ه م'],
            ['med.dean@edocs.test', 'عميد كلية الطب', 'KU-0200', 'ك ط'],
            ['cs.dean@edocs.test', 'عميد كلية علوم الحاسوب', 'KU-0300', 'ك ح'],
            ['students@edocs.test', 'عميد شؤون الطلاب', 'KU-0400', 'ع ش ط'],
        ];

        foreach ($holders as [$email, $name, $no, $code]) {
            // في وضع mang لا توجد الكليات التجريبية؛ يُكلَّف عمداؤها من واجهة الإدارة
            $u = Unit::where('code', $code)->first();
            if (! $u) {
                continue;
            }

            $user = User::create([
                'name' => $name,
                'employee_no' => $no,
                'email' => $email,
                'password' => 'password',
                'home_unit_id' => $u->id,
                'job_title' => $u->head_title,
            ]);

            UnitAssignment::create([
                'unit_id' => $u->id,
                'user_id' => $user->id,
                'kind' => AssignmentKind::Original,
                'start_date' => now()->startOfYear(),
                'decision_no' => 'قرار تجريبي '.$no,
            ]);
        }

        // موظفون عاديون (يتكلمون باسمهم الشخصي وتمر رسائلهم برؤية مديرهم)
        $staff = [
            ['staff.ee@edocs.test', 'موظف — قسم الكهرباء', 'KU-1001', 'ه ك', 'مهندس معمل'],
            ['staff.eng@edocs.test', 'موظف — كلية الهندسة', 'KU-1002', 'ك ه', 'سكرتير الكلية'],
            ['staff.med@edocs.test', 'موظف — كلية الطب', 'KU-1003', 'ك ط', 'إداري'],
            ['staff.hr@edocs.test', 'موظف — الموارد البشرية', 'KU-1004', 'م ب ش', 'ضابط شؤون عاملين'],
        ];

        foreach ($staff as [$email, $name, $no, $code, $title]) {
            if (! Unit::where('code', $code)->exists()) {
                continue;
            }
            User::create([
                'name' => $name,
                'employee_no' => $no,
                'email' => $email,
                'password' => 'password',
                'home_unit_id' => $unit($code)->id,
                'job_title' => $title,
            ]);
        }

        // دور تجريبي: موظف الموارد البشرية يسجّل التكليفات
        User::where('email', 'staff.hr@edocs.test')->first()?->roles()
            ->attach(Role::where('name', 'assignments_officer')->value('id'));
    }
}
