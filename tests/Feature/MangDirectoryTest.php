<?php

namespace Tests\Feature;

use App\Models\Mang\Employee;
use App\Models\Unit;
use App\Models\User;
use App\Services\MangDirectory;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * يحاكي قاعدة الموظفين mang بقاعدة SQLite في الذاكرة بنفس أسماء الجداول والأعمدة.
 */
class MangDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->fakeMang();
    }

    protected function fakeMang(): void
    {
        config(['edocs.mang.enabled' => true]);
        config(['database.connections.mang' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
        ]]);
        DB::purge('mang');
        $s = Schema::connection('mang');

        $s->create('employee_settings', function (Blueprint $t) {
            $t->id();
            $t->string('code');
            $t->string('group');
            $t->string('parent_code')->nullable();
            $t->string('name_ar');
            $t->integer('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
        });
        $s->create('employees', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id')->unique();
            $t->string('first_name');
            $t->string('second_name');
            $t->string('third_name');
            $t->string('fourth_name');
            $t->string('full_name')->nullable();
        });
        $s->create('employee_organizations', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id');
            $t->string('faculty_code')->nullable();
            $t->string('department_code')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
        });
        $s->create('employee_jobs', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id');
            $t->string('job_title_code');
            $t->boolean('is_active')->default(false);
        });
        $s->create('employee_contacts', function (Blueprint $t) {
            $t->id();
            $t->string('employee_id');
            $t->string('work_email')->nullable();
            $t->string('primary_phone')->nullable();
            $t->string('personal_mobile')->nullable();
        });

        $db = DB::connection('mang');
        $db->table('employee_settings')->insert([
            ['code' => '10', 'group' => 'faculty', 'parent_code' => null, 'name_ar' => 'كلية الهندسة (mang)', 'is_active' => 1],
            ['code' => '20', 'group' => 'faculty', 'parent_code' => null, 'name_ar' => 'كلية الطب (mang)', 'is_active' => 1],
            ['code' => '101', 'group' => 'departments', 'parent_code' => '10', 'name_ar' => 'قسم الكهرباء (mang)', 'is_active' => 1],
            ['code' => '201', 'group' => 'departments', 'parent_code' => '20', 'name_ar' => 'قسم الجراحة (mang)', 'is_active' => 1],
            ['code' => '900', 'group' => 'departments', 'parent_code' => null, 'name_ar' => 'قسم بلا كلية', 'is_active' => 1],
            ['code' => '5', 'group' => 'job_title', 'parent_code' => null, 'name_ar' => 'أستاذ مشارك', 'is_active' => 1],
        ]);
        $db->table('employees')->insert([
            ['employee_id' => 'E100', 'first_name' => 'أحمد', 'second_name' => 'محمد', 'third_name' => 'علي', 'fourth_name' => 'عثمان', 'full_name' => 'أحمد محمد علي عثمان'],
            ['employee_id' => 'E200', 'first_name' => 'فاطمة', 'second_name' => 'حسن', 'third_name' => 'إبراهيم', 'fourth_name' => 'آدم', 'full_name' => null],
        ]);
        $db->table('employee_organizations')->insert([
            ['employee_id' => 'E100', 'faculty_code' => '10', 'department_code' => '101', 'start_date' => '2025-01-01', 'end_date' => null],
            ['employee_id' => 'E200', 'faculty_code' => '20', 'department_code' => null, 'start_date' => '2025-01-01', 'end_date' => null],
        ]);
        $db->table('employee_jobs')->insert([
            ['employee_id' => 'E100', 'job_title_code' => '5', 'is_active' => 1],
            ['employee_id' => 'E200', 'job_title_code' => '5', 'is_active' => 1],
        ]);
        $db->table('employee_contacts')->insert([
            ['employee_id' => 'E100', 'work_email' => 'ahmed@kassala.test', 'primary_phone' => '0912000000', 'personal_mobile' => null],
        ]);
    }

    public function test_imports_faculties_and_departments_into_the_tree(): void
    {
        $stats = app(MangDirectory::class)->importUnits();
        $this->assertSame(5, $stats['created']);

        $eng = Unit::forMang('faculty', '10');
        $ee = Unit::forMang('departments', '101');
        $orphan = Unit::forMang('departments', '900');

        $this->assertSame('كلية الهندسة (mang)', $eng->name);
        $this->assertSame('أ ش ع', $eng->parent->code);
        $this->assertSame($eng->id, $ee->parent_id);
        $this->assertStringStartsWith($eng->path, $ee->path);
        $this->assertSame('م ج', $orphan->parent->code);

        // إعادة التشغيل لا تكرر الجهات
        $again = app(MangDirectory::class)->importUnits();
        $this->assertSame(0, $again['created']);
        $this->assertSame(5, $again['skipped']);
    }

    public function test_renamed_faculty_is_updated_on_reimport(): void
    {
        app(MangDirectory::class)->importUnits();
        DB::connection('mang')->table('employee_settings')->where('code', '10')->update(['name_ar' => 'كلية الهندسة والتقنية']);

        $stats = app(MangDirectory::class)->importUnits();
        $this->assertSame(1, $stats['updated']);
        $this->assertSame('كلية الهندسة والتقنية', Unit::forMang('faculty', '10')->name);
    }

    public function test_imports_users_with_home_unit_and_job_title(): void
    {
        $dir = app(MangDirectory::class);
        $dir->importUnits();
        $stats = $dir->importUsers('initial-pass-123');
        $this->assertSame(2, $stats['created']);

        $ahmed = User::where('employee_no', 'E100')->first();
        $this->assertSame('أحمد محمد علي عثمان', $ahmed->name);
        $this->assertSame('أستاذ مشارك', $ahmed->job_title);
        $this->assertSame('ahmed@kassala.test', $ahmed->email);
        $this->assertSame(Unit::forMang('departments', '101')->id, $ahmed->home_unit_id);

        // بلا قسم: جهة العمل = الكلية، والاسم يُركّب من الأجزاء
        $fatima = User::where('employee_no', 'E200')->first();
        $this->assertSame('فاطمة حسن إبراهيم آدم', $fatima->name);
        $this->assertSame(Unit::forMang('faculty', '20')->id, $fatima->home_unit_id);
        $this->assertStringEndsWith('@edocs.local', $fatima->email);
    }

    public function test_sync_user_follows_transfer_in_mang(): void
    {
        $dir = app(MangDirectory::class);
        $dir->importUnits();
        $dir->importUsers('initial-pass-123');

        DB::connection('mang')->table('employee_organizations')->where('employee_id', 'E100')
            ->update(['faculty_code' => '20', 'department_code' => '201']);

        $user = User::where('employee_no', 'E100')->first();
        $this->assertTrue($dir->syncUser($user));
        $this->assertSame(Unit::forMang('departments', '201')->id, $user->fresh()->home_unit_id);
    }

    public function test_mang_models_are_read_only(): void
    {
        $this->expectException(\LogicException::class);
        Employee::find('E100')->save();
    }
}
