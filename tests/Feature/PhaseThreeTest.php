<?php

namespace Tests\Feature;

use App\Livewire\Admin\Templates;
use App\Livewire\Compose;
use App\Livewire\Dashboard;
use App\Livewire\NotificationBell;
use App\Livewire\ShowLetter;
use App\Models\Letter;
use App\Models\Role;
use App\Models\Template;
use App\Models\Unit;
use App\Models\User;
use App\Services\Notifier;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * المرحلة الثالثة: التنبيهات، لوحة المؤشرات، دفتر الوارد والصادر، إدارة النماذج.
 */
class PhaseThreeTest extends TestCase
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
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    private function send(string $as, string $subject, array $to, array $cc = [], ?string $due = null): Letter
    {
        $this->actingAs($this->user($as));
        Livewire::test(Compose::class)
            ->set('subject', $subject)
            ->set('to', array_map(fn ($c) => $this->unit($c)->id, $to))
            ->set('cc', array_map(fn ($c) => $this->unit($c)->id, $cc))
            ->set('dueDate', $due)
            ->call('send')->assertHasNoErrors();

        return Letter::where('subject', $subject)->latest('id')->first();
    }

    /* ---------------- التنبيهات ---------------- */

    public function test_recipients_are_notified_and_opening_marks_read(): void
    {
        $letter = $this->send('eng.dean@edocs.test', 'خطاب للتنبيه', ['م ب ش'], ['ش م']);

        $hr = $this->user('hr@edocs.test');
        $this->assertSame(1, $hr->unreadNotifications()->count());
        $n = $hr->unreadNotifications()->first();
        $this->assertSame('new', $n->data['kind']);
        $this->assertSame($letter->id, $n->data['letter_id']);
        $this->assertSame(1, $this->user('finance@edocs.test')->unreadNotifications()->count()); // النسخة
        $this->assertSame(0, $this->user('eng.dean@edocs.test')->notifications()->count()); // المرسل

        $this->actingAs($hr);
        Livewire::test(NotificationBell::class)->assertSee('1')->set('open', true)->assertSee('خطاب جديد من');
        Livewire::test(ShowLetter::class, ['letter' => $letter]);
        $this->assertSame(0, $hr->fresh()->unreadNotifications()->count());
    }

    public function test_confidential_subject_not_stored_in_notification(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'موضوع حساس')->set('isConfidential', true)
            ->set('to', [$this->unit('م ب ش')->id])->call('send');

        $n = $this->user('hr@edocs.test')->notifications()->first();
        $this->assertSame('خطاب سري', $n->data['subject']);
        $this->assertStringNotContainsString('موضوع حساس', json_encode($n->data, JSON_UNESCAPED_UNICODE));
    }

    public function test_referral_directive_and_reply_notifications(): void
    {
        $letter = $this->send('hr@edocs.test', 'حصر للتنبيه', ['ك ه']);

        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->set('referUnitId', $this->unit('ه ك')->id)->set('referNote', 'للإفادة')
            ->call('refer', $letter->recipients()->first()->id);
        $this->assertSame('referred', $this->user('eng.ee@edocs.test')->notifications()->first()->data['kind']);

        $this->actingAs($this->user('registrar@edocs.test')); // وكيل الجامعة: جد الموارد البشرية
        Livewire::test(ShowLetter::class, ['letter' => $letter])->set('directive', 'نأمل الإسراع')->call('addDirective');
        $this->assertTrue($this->user('hr@edocs.test')->notifications()->get()->contains(fn ($n) => $n->data['kind'] === 'directive'));

        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::withQueryParams(['reply_to' => $letter->id])->test(Compose::class)->call('send');
        $this->assertTrue($this->user('hr@edocs.test')->notifications()->get()->contains(fn ($n) => $n->data['kind'] === 'reply'));
    }

    public function test_daily_reminders_once_each(): void
    {
        $soon = $this->send('eng.dean@edocs.test', 'موعده غدًا', ['م ب ش'], [], today()->addDay()->toDateString());
        $later = $this->send('eng.dean@edocs.test', 'موعده بعد شهر', ['ش م'], [], today()->addMonth()->toDateString());

        $s = app(Notifier::class)->dailyReminders();
        $this->assertSame(['due_soon' => 1, 'overdue' => 0], $s);
        $this->assertSame(['due_soon' => 0, 'overdue' => 0], app(Notifier::class)->dailyReminders()); // لا تكرار

        $this->travel(3)->days();
        $s = app(Notifier::class)->dailyReminders();
        $this->assertSame(1, $s['overdue']);
        $this->assertTrue($this->user('hr@edocs.test')->notifications()->get()->contains(fn ($n) => $n->data['kind'] === 'overdue'));
        $this->assertTrue($this->user('eng.dean@edocs.test')->notifications()->get()->contains(fn ($n) => $n->data['kind'] === 'overdue_sender'));

        // المنجز لا يُذكَّر
        $this->actingAs($this->user('finance@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $later])->call('setStatus', $later->recipients()->first()->id, 'done');
        $this->travel(40)->days();
        $this->assertSame(0, app(Notifier::class)->dailyReminders()['overdue']);
    }

    public function test_broadcast_does_not_flood_notifications(): void
    {
        $this->actingAs($this->user('president@edocs.test'));
        Livewire::withQueryParams(['mode' => 'broadcast'])->test(Compose::class)
            ->set('subject', 'تعميم عام')->set('broadcastScope', 'all_staff')->call('send')->assertHasNoErrors();

        $this->assertSame(0, \DB::table('notifications')->count());
    }

    /* ---------------- لوحة المؤشرات ---------------- */

    public function test_dashboard_metrics_drilldown_and_access(): void
    {
        $l1 = $this->send('hr@edocs.test', 'إلى الكهرباء', ['ه ك']);
        $this->send('hr@edocs.test', 'إلى الكلية', ['ك ه']);
        $this->actingAs($this->user('eng.ee@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $l1])->call('setStatus', $l1->recipients()->first()->id, 'done');

        $this->actingAs($this->user('eng.dean@edocs.test'));
        $c = Livewire::test(Dashboard::class)
            ->assertSet('unitId', $this->unit('ك ه')->id)
            ->assertSee('لوحة المؤشرات')
            ->assertSee('قسم الهندسة الكهربائية');
        $total = $c->viewData('total');
        $this->assertSame(2, $total['incoming']);
        $this->assertSame(1, $total['done']);
        $this->assertSame(50, $total['completion']);

        $c->call('drill', $this->unit('ه ك')->id)->assertSet('unitId', $this->unit('ه ك')->id);
        $c->call('drill', $this->unit('ك ط')->id)->assertSet('unitId', $this->unit('ه ك')->id); // خارج فرعه: يُرفض

        // رابط مباشر لجهة خارج فرعه يعود لجهته
        Livewire::withQueryParams(['unit' => $this->unit('ك ط')->id])->test(Dashboard::class)->assertSet('unitId', $this->unit('ك ه')->id);

        // صلاحية التقارير ترى الجامعة كلها
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('name', 'reports_viewer')->value('id'));
        $this->actingAs($viewer->fresh());
        Livewire::withQueryParams([])->test(Dashboard::class)->assertSet('unitId', $this->unit('م ج')->id);

        $this->actingAs($this->user('staff.ee@edocs.test'));
        $this->get(route('dashboard'))->assertForbidden();
    }

    /* ---------------- دفتر الوارد والصادر ---------------- */

    public function test_register_for_unit_holder_only(): void
    {
        $letter = $this->send('eng.dean@edocs.test', 'قيد في الدفتر', ['م ب ش']);
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $letter]); // استلام برقم وارد

        $params = ['unit' => $this->unit('م ب ش')->id, 'from' => today()->subMonth()->toDateString(), 'to' => today()->toDateString()];
        $this->get(route('reports.register', $params + ['dir' => 'in']))->assertOk()
            ->assertSee('دفتر الوارد')->assertSee('قيد في الدفتر')->assertSee($letter->recipients()->first()->incoming_no);

        $this->actingAs($this->user('eng.dean@edocs.test'));
        $this->get(route('reports.register', ['unit' => $this->unit('ك ه')->id, 'dir' => 'out'] + array_slice($params, 1)))
            ->assertOk()->assertSee($letter->outgoing_no);
        $this->get(route('reports.register', $params + ['dir' => 'in']))->assertForbidden();
    }

    /* ---------------- النماذج ---------------- */

    public function test_create_template_and_use_it(): void
    {
        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Templates::class)
            ->call('create')
            ->set('name', 'طلب عهدة')
            ->set('category', 'الشؤون المالية')
            ->set('allowed_for', 'staff')
            ->set('default_recipient_unit_id', $this->unit('ش م')->id)
            ->set('subject_pattern', 'طلب عهدة {{الغرض}}')
            ->set('body', "أرجو صرف عهدة بمبلغ {{المبلغ}} لغرض {{الغرض}}.\nمقدم الطلب: {{اسم_الموظف}}")
            ->assertCount('fields', 2)
            ->set('fields.1.type', 'number')
            ->call('save')
            ->assertHasNoErrors();

        $t = Template::where('name', 'طلب عهدة')->first();
        $this->assertSame(1, $t->version);
        $this->assertSame(['اسم_الموظف'], collect($t->fields_json)->whereNotNull('auto')->pluck('name')->all());

        $this->actingAs($this->user('staff.ee@edocs.test'));
        Livewire::test(Compose::class)
            ->set('templateId', $t->id)
            ->assertSet('to', [$this->unit('ش م')->id])
            ->assertSee('طلب عهدة');
    }

    public function test_editing_used_template_creates_new_version(): void
    {
        $leave = Template::where('code', 'leave_request')->first();
        $this->actingAs($this->user('staff.ee@edocs.test'));
        Livewire::test(Compose::class)->set('templateId', $leave->id)->call('send')->assertHasNoErrors();

        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Templates::class)
            ->call('edit', $leave->id)
            ->set('body', $leave->body_with_fields."\nملاحظة: يُرفق الجدول.")
            ->call('save')->assertHasNoErrors();

        $this->assertSame(2, Template::where('code', 'leave_request')->max('version'));
        $this->assertFalse($leave->fresh()->is_active);
        $this->assertSame(2, Template::latestActive()->where('code', 'leave_request')->first()->version);
        $this->assertSame($leave->id, Letter::latest('id')->first()->template_id); // الخطاب القديم مرتبط بالإصدار القديم
    }

    public function test_arabic_validation_messages(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', '')->call('send')
            ->assertHasErrors('subject')
            ->assertSee('حقل الموضوع مطلوب');
    }
}
