<?php

namespace Tests\Feature;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\RecipientStatus;
use App\Livewire\Auth\Login;
use App\Livewire\Compose;
use App\Livewire\Mailbox;
use App\Livewire\ShowLetter;
use App\Models\Letter;
use App\Models\LetterEvent;
use App\Models\Template;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * المرحلة الأولى: الدخول، الصناديق، إنشاء الخطاب، الإرسال والاستلام.
 */
class LetterFlowTest extends TestCase
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

    /** العميد يرسل خطابًا للموارد البشرية مع نسخة للمالية */
    private function sendFromEngineering(): Letter
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));

        Livewire::test(Compose::class)
            ->set('subject', 'طلب تعيين معيدين')
            ->set('body', 'نرجو التكرم بالموافقة…')
            ->set('to', [$this->unit('م ب ش')->id])
            ->set('cc', [$this->unit('ش م')->id])
            ->set('priority', 'urgent')
            ->call('send')
            ->assertHasNoErrors();

        return Letter::latest('id')->first();
    }

    public function test_login_with_email_or_employee_number(): void
    {
        Livewire::test(Login::class)
            ->set('login', 'hr@edocs.test')->set('password', 'wrong')
            ->call('authenticate')
            ->assertHasErrors('login');
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('login', 'KU-0010')->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('mail.box', 'inbox'));
        $this->assertAuthenticatedAs($this->user('hr@edocs.test'));
    }

    public function test_guest_is_redirected_and_pages_render(): void
    {
        $this->get('/mail/inbox')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('المراسلات الإلكترونية');

        $letter = $this->sendFromEngineering();

        $this->actingAs($this->user('hr@edocs.test'));
        $this->get('/mail/inbox')->assertOk()->assertSee('طلب تعيين معيدين');
        $this->get('/letters/new')->assertOk()->assertSee('خطاب جديد');
        $this->get('/letters/'.$letter->id)->assertOk()->assertSee($letter->outgoing_no);

        // جهة لا علاقة لها: ممنوع
        $this->actingAs($this->user('med.dean@edocs.test'));
        $this->get('/letters/'.$letter->id)->assertForbidden();
    }

    public function test_send_assigns_outgoing_number_and_reaches_recipients(): void
    {
        $letter = $this->sendFromEngineering();

        $this->assertSame(LetterStatus::Sent, $letter->status);
        $this->assertSame('جك/ك ه/'.now()->year.'/0001', $letter->outgoing_no);
        $this->assertSame(2, $letter->recipients()->count());
        $this->assertSame($this->user('eng.dean@edocs.test')->id, $letter->approver_user_id);

        // يظهر في وارد الموارد البشرية والمالية، وفي صادر الكلية
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertSee('طلب تعيين معيدين');
        $this->actingAs($this->user('finance@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertSee('طلب تعيين معيدين');
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'outbox'])->assertSee('طلب تعيين معيدين');
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertDontSee('طلب تعيين معيدين');

        // مدير الجامعة يراه في المتابعة، ورئيس قسم تابع للعميد لا يراه (الرؤية لا تصعد)
        $this->actingAs($this->user('president@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'following'])->assertSee('طلب تعيين معيدين');
        $this->actingAs($this->user('eng.ee@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'following'])->assertDontSee('طلب تعيين معيدين');
    }

    public function test_opening_receives_with_incoming_number_and_status_flow(): void
    {
        $letter = $this->sendFromEngineering();
        $hr = $this->user('hr@edocs.test');
        $row = $letter->recipients()->where('unit_id', $this->unit('م ب ش')->id)->first();

        $this->actingAs($hr);
        $c = Livewire::test(ShowLetter::class, ['letter' => $letter]);

        $row->refresh();
        $this->assertSame(RecipientStatus::Received, $row->status);
        $this->assertSame('جك/م ب ش/و/'.now()->year.'/0001', $row->incoming_no);

        $c->call('setStatus', $row->id, 'in_progress')->assertHasNoErrors();
        $c->set('statusNote', 'تمت المعالجة')->call('setStatus', $row->id, 'done');
        $this->assertSame(RecipientStatus::Done, $row->fresh()->status);

        $c->call('setStatus', $row->id, 'archived');
        $this->assertSame(RecipientStatus::Archived, $row->fresh()->status);

        Livewire::test(Mailbox::class, ['box' => 'archive'])->assertSee('طلب تعيين معيدين');
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertDontSee('طلب تعيين معيدين');

        $this->assertTrue($letter->events()->where('action', LetterEvent::STATUS_CHANGED)->where('note', 'تمت المعالجة')->exists());

        // جهة النسخة (المالية) لم تستلم بعد
        $this->assertSame(RecipientStatus::Pending, $letter->recipients()->where('unit_id', $this->unit('ش م')->id)->first()->status);
    }

    public function test_ancestor_can_add_directive_but_not_change_status(): void
    {
        $letter = $this->sendFromEngineering();
        $row = $letter->recipients()->first();

        $this->actingAs($this->user('president@edocs.test'));
        $c = Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->set('directive', 'للإفادة بالموقف خلال أسبوع')
            ->call('addDirective')
            ->assertHasNoErrors();

        $this->assertTrue($letter->events()->where('action', LetterEvent::DIRECTIVE)->exists());

        $c->call('setStatus', $row->id, 'done')->assertForbidden();
        $this->assertSame(RecipientStatus::Pending, $row->fresh()->status); // الاطلاع لا يغيّر الحالة
    }

    public function test_draft_lifecycle_and_discard(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));

        Livewire::test(Compose::class)
            ->set('subject', 'مسودة اختبار')
            ->set('to', [$this->unit('م ب ش')->id])
            ->call('saveDraft')
            ->assertHasNoErrors();

        $draft = Letter::where('subject', 'مسودة اختبار')->first();
        $this->assertSame(LetterStatus::Draft, $draft->status);
        $this->assertNull($draft->outgoing_no);
        $this->assertFalse($draft->isVisibleTo($this->user('hr@edocs.test')));

        Livewire::test(Mailbox::class, ['box' => 'drafts'])->assertSee('مسودة اختبار');

        Livewire::test(Compose::class, ['letter' => $draft])
            ->assertSet('subject', 'مسودة اختبار')
            ->call('discard');
        $this->assertNull(Letter::find($draft->id));
    }

    public function test_validation_and_cannot_send_as_someone_elses_unit(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));

        Livewire::test(Compose::class)
            ->set('subject', '')
            ->set('to', [])
            ->call('send')
            ->assertHasErrors(['subject', 'to']);

        Livewire::test(Compose::class)
            ->set('unitId', $this->unit('م ج')->id) // ليس مكلّفًا بها
            ->set('subject', 'محاولة')
            ->set('to', [$this->unit('م ب ش')->id])
            ->call('send')
            ->assertForbidden();
        $this->assertFalse(Letter::where('subject', 'محاولة')->exists());
    }

    public function test_staff_personal_request_goes_only_to_allowed_units(): void
    {
        $staff = $this->user('staff.ee@edocs.test');
        $this->actingAs($staff);

        $c = Livewire::test(Compose::class)->assertSet('mode', 'personal');

        // الموارد البشرية مسموحة، مكتب المدير غير مسموح
        $this->assertTrue($c->instance()->recipientOptions->contains('id', $this->unit('م ب ش')->id));
        $this->assertFalse($c->instance()->recipientOptions->contains('id', $this->unit('م ج')->id));

        $c->set('templateId', Template::where('code', 'leave_request')->value('id'))
            ->assertSet('to', [$this->unit('م ب ش')->id])
            ->assertSet('subject', 'طلب إجازة [نوع الإجازة]')
            ->set('subject', 'طلب إجازة سنوية')
            ->call('send')
            ->assertHasNoErrors();

        $letter = Letter::latest('id')->first();
        $this->assertNull($letter->sender_unit_id);
        $this->assertSame($staff->home_unit_id, $letter->origin_unit_id);
        $this->assertStringContainsString($staff->name, $letter->body);

        // رئيس القسم (المدير المباشر) يراه في المتابعة، والموارد البشرية في الوارد
        $this->actingAs($this->user('eng.ee@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'following'])->assertSee('طلب إجازة سنوية');
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertSee('طلب إجازة سنوية');

        // محاولة توجيه طلب شخصي لجهة غير مسموحة
        $this->actingAs($staff);
        Livewire::test(Compose::class)
            ->set('subject', 'محاولة')
            ->set('to', [$this->unit('م ج')->id])
            ->call('send')
            ->assertHasErrors('to');
    }

    public function test_sender_can_cancel_with_reason_and_letter_is_kept(): void
    {
        $letter = $this->sendFromEngineering();

        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->set('cancelReason', 'قصير')
            ->call('cancel')
            ->assertHasErrors('cancelReason')
            ->set('cancelReason', 'أُرسل بالخطأ إلى جهة غير معنية')
            ->call('cancel')
            ->assertHasNoErrors();

        $letter->refresh();
        $this->assertSame(LetterStatus::Cancelled, $letter->status);
        $this->assertTrue($letter->isVisibleTo($this->user('hr@edocs.test')));

        $this->expectException(\LogicException::class);
        $letter->delete();
    }

    public function test_reply_links_to_parent_and_goes_back_to_sender(): void
    {
        $letter = $this->sendFromEngineering();
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $letter]);

        Livewire::withQueryParams(['reply_to' => $letter->id])->test(Compose::class)
            ->assertSet('to', [$this->unit('ك ه')->id])
            ->assertSet('parentLetterId', $letter->id)
            ->call('send')
            ->assertHasNoErrors();

        $reply = Letter::latest('id')->first();
        $this->assertSame($letter->id, $reply->parent_letter_id);
        $this->assertSame(LetterType::Reply, $reply->type);

        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertSee('رد: طلب تعيين معيدين');
    }
}
