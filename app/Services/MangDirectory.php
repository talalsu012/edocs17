<?php

namespace App\Services;

use App\Enums\UnitType;
use App\Models\Mang\Employee;
use App\Models\Mang\Setting;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * الجسر مع قاعدة الموظفين mang.
 *
 * - الكليات والأقسام: تُنشأ لها جهات في شجرة edocs مرة واحدة (لأن الأرشيف والرؤية الهرمية
 *   مبنية على الشجرة)، وتُحدَّث أسماؤها وتبعيتها كلما شُغّل الاستيراد.
 * - بيانات الموظف (الاسم، المسمى الوظيفي، الكلية/القسم): تُقرأ مباشرة من mang،
 *   وتُحدَّث نسخة احتياطية منها في users عند كل دخول.
 */
class MangDirectory
{
    public const FACULTY = 'faculty';

    public const DEPARTMENT = 'departments';

    /**
     * استيراد/تحديث الكليات والأقسام من mang.
     *
     * @return array{created:int, updated:int, skipped:int}
     */
    public function importUnits(bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0];

        $facultiesParent = Unit::where('code', config('edocs.mang.faculties_parent_unit'))->first();
        $orphansParent = Unit::where('code', config('edocs.mang.orphan_departments_parent_unit'))->first()
            ?? Unit::roots()->first();

        if (! $facultiesParent || ! $orphansParent) {
            throw new \RuntimeException('لم يتم العثور على الجهة الأم للكليات أو الأقسام؛ راجع config/edocs.php');
        }

        foreach (Setting::group('faculty')->active()->orderBy('sort_order')->get() as $i => $f) {
            $this->upsertUnit(self::FACULTY, $f, $facultiesParent, UnitType::College, 'ك', $i, $dryRun, $stats);
        }

        foreach (Setting::group('department')->active()->orderBy('sort_order')->get() as $i => $d) {
            $parent = Unit::forMang(self::FACULTY, $d->parentCode()) ?? $orphansParent;
            $this->upsertUnit(self::DEPARTMENT, $d, $parent, UnitType::Department, 'ق', $i, $dryRun, $stats);
        }

        return $stats;
    }

    protected function upsertUnit(string $group, Setting $s, Unit $parent, UnitType $type, string $prefix, int $order, bool $dryRun, array &$stats): void
    {
        $unit = Unit::forMang($group, (string) $s->code);

        if ($dryRun) {
            $unit ? $stats['updated']++ : $stats['created']++;

            return;
        }

        if (! $unit) {
            Unit::create([
                'name' => $s->name_ar,
                'code' => $this->uniqueCode($prefix.' '.$s->code),
                'type' => $type,
                'mang_group' => $group,
                'mang_code' => (string) $s->code,
                'parent_id' => $parent->id,
                'head_title' => $type === UnitType::College ? 'العميد' : 'رئيس القسم',
                'sort_order' => $order,
            ]);
            $stats['created']++;

            return;
        }

        $unit->fill(['name' => $s->name_ar, 'parent_id' => $parent->id]);
        if ($unit->isDirty()) {
            $unit->save();
            $stats['updated']++;
        } else {
            $stats['skipped']++;
        }
    }

    protected function uniqueCode(string $code): string
    {
        $candidate = Str::limit($code, 30, '');
        $n = 1;
        while (Unit::where('code', $candidate)->exists()) {
            $candidate = Str::limit($code, 26, '').'-'.$n++;
        }

        return $candidate;
    }

    /* ---------------- الموظفون ---------------- */

    public function findEmployee(?string $employeeNo): ?Employee
    {
        if (! $employeeNo || ! config('edocs.mang.enabled')) {
            return null;
        }

        return Employee::query()
            ->with(['organization.faculty', 'organization.department', 'job.jobTitle', 'contact'])
            ->find($employeeNo);
    }

    /** جهة عمل الموظف في edocs بحسب قسمه (أو كليته) في mang. */
    public function resolveHomeUnit(Employee $employee): ?Unit
    {
        $org = $employee->organization;
        if (! $org) {
            return null;
        }

        return Unit::forMang(self::DEPARTMENT, $org->department_code)
            ?? Unit::forMang(self::FACULTY, $org->faculty_code);
    }

    /**
     * تحديث بيانات المستخدم من mang (الاسم، المسمى، جهة العمل). يُستدعى عند الدخول.
     * يعيد false إن لم يوجد الموظف في mang.
     */
    public function syncUser(User $user): bool
    {
        $employee = $this->findEmployee($user->employee_no);
        if (! $employee) {
            return false;
        }

        $home = $this->resolveHomeUnit($employee);

        $user->forceFill([
            'name' => $employee->displayName() ?: $user->name,
            'job_title' => $employee->jobTitle() ?? $user->job_title,
            'home_unit_id' => $home?->id ?? $user->home_unit_id,
            'phone' => $employee->contact?->primary_phone ?: $employee->contact?->personal_mobile ?: $user->phone,
        ])->save();

        return true;
    }

    /**
     * إنشاء حسابات edocs للموظفين النشطين في mang (ومن له حساب يُحدَّث فقط).
     *
     * @return array{created:int, updated:int}
     */
    public function importUsers(string $defaultPassword, bool $dryRun = false): array
    {
        $stats = ['created' => 0, 'updated' => 0];

        Employee::query()
            ->with(['organization', 'job.jobTitle', 'contact'])
            ->whereHas('job')
            ->orderBy('employee_id')
            ->chunk(200, function ($employees) use (&$stats, $defaultPassword, $dryRun) {
                foreach ($employees as $e) {
                    $user = User::where('employee_no', $e->employee_id)->first();

                    if ($dryRun) {
                        $user ? $stats['updated']++ : $stats['created']++;

                        continue;
                    }

                    if ($user) {
                        $this->syncUser($user);
                        $stats['updated']++;

                        continue;
                    }

                    $email = $e->contact?->work_email;
                    if (! $email || User::where('email', $email)->exists()) {
                        $email = Str::slug((string) $e->employee_id, '').'@edocs.local';
                    }

                    User::create([
                        'name' => $e->displayName(),
                        'employee_no' => (string) $e->employee_id,
                        'email' => $email,
                        'password' => $defaultPassword,
                        'job_title' => $e->jobTitle(),
                        'home_unit_id' => $this->resolveHomeUnit($e)?->id,
                    ]);
                    $stats['created']++;
                }
            });

        return $stats;
    }
}
