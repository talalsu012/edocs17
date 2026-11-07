<?php

namespace Tests\Feature;

use App\Enums\NumberDirection;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Users;
use App\Livewire\Compose;
use App\Models\Letter;
use App\Models\Numbering;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StaffAllowedTarget;
use App\Models\Unit;
use App\Models\User;
use App\Services\TrainingData;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * إعدادات الجامعة، الأدوار والصلاحيات، إدارة المستخدمين، بيانات التدريب.
 */
class AdministrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prepare();
    }

    protected function prepare(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        Setting::flush();
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    /* ---------------- الإعدادات ---------------- */

    public function test_settings_update_prefix_names_and_staff_targets(): void
    {
        $this->actingAs($this->user('admin@edocs.test'));

        Livewire::test(Settings::class)
            ->assertSet('university_name_ar', 'جامعة كسلا')
            ->set('number_prefix', 'ج.ك')
            ->set('university_name_en', 'University of Kassala')
            ->set('letterhead_phone', '0411-000000')
            ->set('show_hijri', false)
            ->set('staffTargets', [$this->unit('م ب ش')->id])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('ج.ك', Setting::get('number_prefix'));
        $this->assertFalse(Setting::get('show_hijri'));
        $this->assertSame([$this->unit('م ب ش')->id], StaffAllowedTarget::pluck('unit_id')->all());

        $no = Numbering::next($this->unit('ك ه'), NumberDirection::Outgoing, 2026);
        $this->assertSame('ج.ك/ك ه/2026/0001', $no['number']);

        // الطباعة تستخدم الإعدادات
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'اختبار الترويسة')->set('to', [$this->unit('م ب ش')->id])->call('send');
        $letter = Letter::where('subject', 'اختبار الترويسة')->first();
        $this->get(route('letters.print', $letter))->assertOk()
            ->assertSee('University of Kassala')->assertSee('0411-000000')->assertDontSee('الموافق:');
    }

    public function test_settings_validation_and_permission(): void
    {
        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Settings::class)
            ->set('university_name_ar', '')
            ->set('allowed_extensions', 'pdf; rm -rf')
            ->call('save')
            ->assertHasErrors(['university_name_ar', 'allowed_extensions']);

        $this->actingAs($this->user('hr@edocs.test'));
        $this->get(route('admin.settings'))->assertForbidden();
    }

    /* ---------------- الأدوار والصلاحيات ---------------- */

    public function test_roles_grant_admin_screens(): void
    {
        $hr = $this->user('hr@edocs.test');
        $this->assertFalse($hr->hasPermission('assignments.manage'));

        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Roles::class)
            ->call('create')
            ->set('label', 'مسؤول الإعدادات')
            ->set('name', 'settings_officer')
            ->set('permissions', ['settings.manage', 'users.manage'])
            ->call('save')
            ->assertHasNoErrors();

        $role = Role::where('name', 'settings_officer')->first();
        $this->assertEqualsCanonicalizing(['settings.manage', 'users.manage'], $role->permissions());

        Livewire::test(Users::class)
            ->call('select', $hr->id)
            ->set('roleIds', [$role->id])
            ->call('save')
            ->assertHasNoErrors();

        $hr = $hr->fresh();
        $this->assertTrue($hr->hasPermission('settings.manage'));
        $this->assertFalse($hr->hasPermission('roles.manage'));

        $this->actingAs($hr);
        $this->get(route('admin.settings'))->assertOk();
        $this->get(route('admin.users'))->assertOk();
        $this->get(route('admin.roles'))->assertForbidden();
        $this->get(route('admin.assignments'))->assertForbidden();
        $this->get('/mail/inbox')->assertOk()->assertSee('إعدادات الجامعة')->assertDontSee('الأدوار والصلاحيات');
    }

    public function test_seeded_assignments_officer_role_and_system_roles_are_protected(): void
    {
        $officer = $this->user('staff.hr@edocs.test');
        $this->assertTrue($officer->hasPermission('assignments.manage'));
        $this->actingAs($officer);
        $this->get(route('admin.assignments'))->assertOk();

        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Roles::class)->call('delete', Role::where('name', 'it_admin')->value('id'))->assertForbidden();
        $this->assertTrue(Role::where('name', 'it_admin')->exists());
    }

    public function test_admin_role_does_not_grant_reading_letters(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'خطاب خاص')->set('to', [$this->unit('م ب ش')->id])->call('send');
        $letter = Letter::where('subject', 'خطاب خاص')->first();

        $this->assertFalse($letter->isVisibleTo($this->user('admin@edocs.test')));
        $this->assertFalse($letter->isVisibleTo($this->user('staff.hr@edocs.test'))); // دور التكليفات
    }

    /* ---------------- المستخدمون ---------------- */

    public function test_deactivate_user_and_reset_password(): void
    {
        $staff = $this->user('staff.med@edocs.test');
        $this->actingAs($this->user('admin@edocs.test'));

        Livewire::test(Users::class)
            ->set('search', 'KU-1003')
            ->assertSee($staff->name)
            ->call('select', $staff->id)
            ->set('isActive', false)
            ->set('newPassword', 'NewPass#2026')
            ->call('save')
            ->assertHasNoErrors();

        $staff = $staff->fresh();
        $this->assertFalse($staff->is_active);
        $this->assertTrue(\Hash::check('NewPass#2026', $staff->password));
        $this->assertFalse(User::where('email', $staff->email)->where('is_active', true)->exists()); // الدخول يشترط is_active
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $admin = $this->user('admin@edocs.test');
        $this->actingAs($admin);

        Livewire::test(Users::class)
            ->call('select', $admin->id)
            ->set('isActive', false)
            ->call('save')
            ->assertHasErrors('isActive');
        $this->assertTrue($admin->fresh()->is_active);
    }

    /* ---------------- بيانات التدريب ---------------- */

    public function test_training_data_generate_and_purge(): void
    {
        $realBefore = Numbering::next($this->unit('م ب ش'), NumberDirection::Outgoing)['seq']; // رقم حقيقي واحد

        $s = app(TrainingData::class)->generate();
        $this->assertGreaterThanOrEqual(20, $s['letters']);
        $this->assertGreaterThan(0, $s['attachments']);
        $this->assertSame($s['letters'], Letter::where('is_training', true)->count());
        $this->assertTrue(Letter::where('type', 'broadcast')->exists());
        $this->assertTrue(Letter::where('type', 'staff_request')->exists());
        $this->assertTrue(Letter::where('status', 'cancelled')->exists());
        $this->assertTrue(\DB::table('letter_recipients')->where('role', 'referred')->exists());
        $this->assertTrue(Letter::where('sent_at', '<', now()->subDays(30))->exists()); // موزعة زمنيًا

        $first = Letter::where('is_training', true)->with('attachments')->whereHas('attachments')->first();
        \Storage::disk('local')->assertExists($first->attachments->first()->file_path);

        $deleted = app(TrainingData::class)->purge();
        $this->assertSame($s['letters'], $deleted);
        $this->assertSame(0, Letter::count());
        \Storage::disk('local')->assertMissing($first->attachments->first()->file_path);

        // العداد يعود؛ الرقم الحقيقي غير مرتبط بخطاب فيعود للصفر
        $this->assertSame(0, Numbering::where('unit_id', $this->unit('ك ه')->id)->value('last_no') ?? 0);
        $this->assertLessThanOrEqual($realBefore, (int) Numbering::where('unit_id', $this->unit('م ب ش')->id)->where('direction', 'out')->value('last_no'));
    }
}
