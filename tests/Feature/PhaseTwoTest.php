<?php

namespace Tests\Feature;

use App\Enums\RecipientRole;
use App\Enums\RecipientStatus;
use App\Livewire\Admin\Assignments;
use App\Livewire\Compose;
use App\Livewire\Mailbox;
use App\Livewire\ShowLetter;
use App\Models\Letter;
use App\Models\Unit;
use App\Models\User;
use App\Services\AssignmentService;
use App\Services\BroadcastService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * المرحلة الثانية: التعميم، الإحالة، التكليفات والتنقلات، الطباعة والتحقق.
 */
class PhaseTwoTest extends TestCase
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

    private function broadcast(string $as, array $set): ?Letter
    {
        $this->actingAs($this->user($as));
        $c = Livewire::withQueryParams(['mode' => 'broadcast'])->test(Compose::class)
            ->assertSet('mode', 'broadcast')
            ->set('subject', 'تعميم بشأن الدوام')
            ->set('body', 'يبدأ الدوام الساعة الثامنة.');
        foreach ($set as $k => $v) {
            $c->set($k, $v);
        }
        $c->call('send');

        return Letter::where('subject', 'تعميم بشأن الدوام')->latest('id')->first();
    }

    /* ---------------- التعميم ---------------- */

    public function test_dean_broadcasts_to_own_subtree_with_acknowledgement_stats(): void
    {
        $letter = $this->broadcast('eng.dean@edocs.test', []); // الفرع الافتراضي: جهته
        $this->assertNotNull($letter->outgoing_no);
        $this->assertSame(0, $letter->recipients()->count());

        $svc = app(BroadcastService::class);
        // الجمهور: العميد، رئيسا القسمين، وموظفا الكلية/القسم
        $this->assertSame(5, $svc->stats($letter)['total']);
        $this->assertTrue($svc->isInAudience($this->user('staff.ee@edocs.test'), $letter));
        $this->assertFalse($svc->isInAudience($this->user('hr@edocs.test'), $letter));

        // الموظف يراه في التعاميم ويضغط «علمت»
        $this->actingAs($this->user('staff.ee@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'broadcasts'])->assertSee('تعميم بشأن الدوام');
        Livewire::test(ShowLetter::class, ['letter' => $letter])->call('acknowledge')->assertHasNoErrors();
        $this->assertSame(['read' => 1, 'total' => 5, 'percent' => 20], $svc->stats($letter->fresh()));

        // من خارج الفرع لا يرى ولا يستطيع «علمت»
        $this->actingAs($this->user('hr@edocs.test'));
        $this->get(route('letters.show', $letter))->assertForbidden();

        // العميد يرى النسبة
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $letter])->assertSee('نسبة الاطلاع')->assertSee('20٪');
    }

    public function test_unauthorized_unit_cannot_broadcast_university_wide(): void
    {
        $letter = $this->broadcast('eng.dean@edocs.test', ['broadcastScope' => 'all_staff']);
        $this->assertNull($letter); // رُفض

        // ولا على فرع خارج شجرته
        $letter = $this->broadcast('eng.dean@edocs.test', ['broadcastRoot' => $this->unit('ك ط')->id]);
        $this->assertNull($letter);
    }

    public function test_authorized_office_broadcasts_to_all_colleges(): void
    {
        $letter = $this->broadcast('president@edocs.test', ['broadcastScope' => 'unit_type', 'broadcastType' => 'college']);
        $this->assertNotNull($letter);

        $svc = app(BroadcastService::class);
        $this->assertTrue($svc->isInAudience($this->user('med.dean@edocs.test'), $letter));
        $this->assertTrue($svc->isInAudience($this->user('staff.eng@edocs.test'), $letter));
        $this->assertFalse($svc->isInAudience($this->user('finance@edocs.test'), $letter));

        $all = $this->broadcast('president@edocs.test', ['broadcastScope' => 'all_staff']);
        $this->assertSame(User::where('is_active', true)->count(), $svc->stats($all)['total']);
    }

    /* ---------------- الإحالة ---------------- */

    public function test_referral_to_subordinate_unit(): void
    {
        // الموارد البشرية ترسل لكلية الهندسة
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'حصر أعضاء هيئة التدريس')
            ->set('to', [$this->unit('ك ه')->id])->call('send')->assertHasNoErrors();
        $letter = Letter::where('subject', 'حصر أعضاء هيئة التدريس')->first();

        // العميد يحيله لقسم الكهرباء
        $this->actingAs($this->user('eng.dean@edocs.test'));
        $row = $letter->recipients()->first();
        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->set('referUnitId', $this->unit('ه ك')->id)
            ->set('referNote', 'للإفادة')
            ->call('refer', $row->id)
            ->assertHasNoErrors();

        $this->assertSame(RecipientStatus::Referred, $row->fresh()->status);
        $new = $letter->recipients()->where('role', RecipientRole::Referred->value)->first();
        $this->assertSame($this->unit('ه ك')->id, $new->unit_id);
        $this->assertSame($row->id, $new->referred_from_id);
        $this->assertSame('للإفادة', $new->referral_note);

        // رئيس القسم يراه في الوارد ويستطيع إنجازه
        $this->actingAs($this->user('eng.ee@edocs.test'));
        Livewire::test(Mailbox::class, ['box' => 'inbox'])->assertSee('حصر أعضاء هيئة التدريس');
        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->assertSee('محال من')
            ->call('setStatus', $new->id, 'done')->assertHasNoErrors();
        $this->assertSame(RecipientStatus::Done, $new->fresh()->status);

        // لا تتكرر الإحالة لنفس الجهة
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(ShowLetter::class, ['letter' => $letter])
            ->set('referUnitId', $this->unit('ه م')->id)
            ->call('refer', $row->id)
            ->assertHasErrors('referUnitId'); // حالته الآن «محال»
    }

    /* ---------------- التكليفات ---------------- */

    public function test_admin_page_is_admin_only(): void
    {
        $this->actingAs($this->user('president@edocs.test'));
        $this->get(route('admin.assignments'))->assertForbidden();

        $this->actingAs($this->user('admin@edocs.test'));
        $this->get(route('admin.assignments'))->assertOk()->assertSee('التكليفات والتنقلات');
    }

    public function test_transfer_new_dean_inherits_mailbox_and_old_loses_it(): void
    {
        $this->actingAs($this->user('hr@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'خطاب لكلية الطب')
            ->set('to', [$this->unit('ك ط')->id])->call('send');
        $letter = Letter::where('subject', 'خطاب لكلية الطب')->first();

        $newDean = User::factory()->create(['name' => 'العميد الجديد']);
        $old = $this->user('med.dean@edocs.test');

        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Assignments::class)
            ->call('selectUnit', $this->unit('ك ط')->id)
            ->call('pickUser', $newDean->id)
            ->set('decisionNo', 'ق/2026/15')
            ->call('assign')
            ->assertHasNoErrors();

        $this->assertSame($newDean->id, $this->unit('ك ط')->currentHolder()->id);
        $this->assertTrue($letter->isVisibleTo($newDean->fresh()));
        $this->assertFalse($letter->isVisibleTo($old->fresh()));
        $this->assertNotNull($old->assignments()->first()->end_date);
    }

    public function test_acting_assignment_expires_automatically(): void
    {
        $acting = $this->user('eng.civil@edocs.test');
        $ee = $this->unit('ه ك');

        app(AssignmentService::class)->assign($this->user('admin@edocs.test'), $ee, $acting, [
            'kind' => 'acting', 'start_date' => today()->toDateString(), 'end_date' => today()->addDays(2)->toDateString(),
        ]);

        $this->assertSame($acting->id, $ee->fresh()->currentHolder()->id);
        $this->assertTrue($acting->fresh()->holdsUnit($ee)); // يمثل جهتين خلال الإنابة

        // ولا تُنهى تكليفاته الأصلية
        $this->assertTrue($acting->fresh()->holdsUnit($this->unit('ه م')));

        $this->travel(3)->days();
        $this->assertSame($this->user('eng.ee@edocs.test')->id, $ee->fresh()->currentHolder()->id);
        $this->assertFalse($acting->fresh()->holdsUnit($ee));
    }

    public function test_acting_requires_end_date_and_handover_report(): void
    {
        $this->actingAs($this->user('admin@edocs.test'));
        Livewire::test(Assignments::class)
            ->call('selectUnit', $this->unit('ه ك')->id)
            ->call('pickUser', $this->user('eng.civil@edocs.test')->id)
            ->set('kind', 'acting')
            ->call('assign')
            ->assertHasErrors('endDate');

        $this->get(route('admin.handover', $this->unit('م ب ش')))->assertOk()->assertSee('تقرير تسليم وتسلّم');
    }

    /* ---------------- الطباعة والتحقق ---------------- */

    public function test_print_and_public_verification(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'خطاب للطباعة')
            ->set('to', [$this->unit('م ب ش')->id])->set('cc', [$this->unit('ش م')->id])->call('send');
        $letter = Letter::where('subject', 'خطاب للطباعة')->first();

        $this->get(route('letters.print', $letter))->assertOk()
            ->assertSee($letter->outgoing_no)
            ->assertSee('صورة إلى:')
            ->assertSee($letter->verificationUrl(), false);

        $this->actingAs($this->user('med.dean@edocs.test'));
        $this->get(route('letters.print', $letter))->assertForbidden();

        auth()->logout();
        $this->get($letter->verificationUrl())->assertOk()->assertSee('خطاب صحيح')->assertSee('خطاب للطباعة');
        $this->get(route('letters.verify', ['letter' => $letter->id, 'code' => 'tampered123']))->assertNotFound();
    }

    public function test_verification_hides_confidential_subject(): void
    {
        $this->actingAs($this->user('eng.dean@edocs.test'));
        Livewire::test(Compose::class)->set('subject', 'موضوع سري جدًا')->set('isConfidential', true)
            ->set('to', [$this->unit('م ب ش')->id])->call('send');
        $letter = Letter::where('subject', 'موضوع سري جدًا')->first();

        auth()->logout();
        $this->get($letter->verificationUrl())->assertOk()->assertSee('خطاب صحيح')->assertDontSee('موضوع سري جدًا');
    }
}
