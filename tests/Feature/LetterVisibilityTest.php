<?php

namespace Tests\Feature;

use App\Enums\AssignmentKind;
use App\Enums\BroadcastScope;
use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\NumberDirection;
use App\Enums\RecipientRole;
use App\Enums\UnitType;
use App\Models\Letter;
use App\Models\Numbering;
use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * يتحقق من أمثلة قواعد الرؤية الهرمية في القسم 4 من التحليل.
 */
class LetterVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function unit(string $code): Unit
    {
        return Unit::where('code', $code)->firstOrFail();
    }

    /** خطاب مرسل من جهة إلى جهات */
    private function unitLetter(string $from, array $to, bool $confidential = false, LetterStatus $status = LetterStatus::Sent): Letter
    {
        $fromUnit = $this->unit($from);
        $letter = Letter::create([
            'type' => count($to) > 1 ? LetterType::Multi : LetterType::Direct,
            'subject' => 'خطاب اختبار',
            'sender_unit_id' => $fromUnit->id,
            'origin_unit_id' => $fromUnit->id,
            'sender_user_id' => $fromUnit->currentHolder()->id,
            'is_confidential' => $confidential,
            'status' => $status,
        ]);
        foreach ($to as $code) {
            $letter->recipients()->create(['unit_id' => $this->unit($code)->id, 'role' => RecipientRole::To]);
        }

        return $letter;
    }

    /** طلب شخصي من موظف إلى جهة */
    private function staffRequest(string $email, string $to, bool $confidential = false): Letter
    {
        $staff = $this->user($email);
        $letter = Letter::create([
            'type' => LetterType::StaffRequest,
            'subject' => 'طلب إجازة',
            'sender_unit_id' => null,
            'origin_unit_id' => $staff->home_unit_id,
            'sender_user_id' => $staff->id,
            'is_confidential' => $confidential,
            'status' => LetterStatus::Sent,
        ]);
        $letter->recipients()->create(['unit_id' => $this->unit($to)->id, 'role' => RecipientRole::To]);

        return $letter;
    }

    private function sees(string $email, Letter $letter): bool
    {
        return $letter->isVisibleTo($this->user($email));
    }

    public function test_tree_paths_are_built(): void
    {
        $root = $this->unit('م ج');
        $ee = $this->unit('ه ك');

        $this->assertSame('/'.$root->id.'/', $root->path);
        $this->assertStringStartsWith($root->path, $ee->path);
        $this->assertSame(4, $ee->depth); // م ج › ن م › أ ش ع › ك ه › ه ك
        $this->assertTrue($this->unit('ك ه')->isAncestorOf($ee));
        $this->assertSame(['م ج', 'ن م', 'أ ش ع', 'ك ه'], $ee->ancestors()->pluck('code')->all());
    }

    public function test_moving_a_unit_rebuilds_descendant_paths(): void
    {
        $eng = $this->unit('ك ه');
        $eng->update(['parent_id' => $this->unit('م ج')->id]);

        $ee = $this->unit('ه ك');
        $this->assertSame($eng->fresh()->path.$ee->id.'/', $ee->path);
        $this->assertSame(2, $ee->depth);
    }

    public function test_president_sees_everything(): void
    {
        $letter = $this->unitLetter('ك ط', ['ش م']);
        $this->assertTrue($this->sees('president@edocs.test', $letter));
    }

    public function test_engineering_dean_does_not_see_medicine_with_finance(): void
    {
        $letter = $this->unitLetter('ك ط', ['ش م']);

        $this->assertFalse($this->sees('eng.dean@edocs.test', $letter));
        $this->assertTrue($this->sees('med.dean@edocs.test', $letter));
        $this->assertTrue($this->sees('finance@edocs.test', $letter));
        $this->assertTrue($this->sees('registrar@edocs.test', $letter)); // جد المالية
    }

    public function test_visibility_does_not_go_up(): void
    {
        $letter = $this->unitLetter('ك ه', ['م ج']);

        $this->assertTrue($this->sees('eng.dean@edocs.test', $letter));
        $this->assertFalse($this->sees('eng.ee@edocs.test', $letter));
    }

    public function test_staff_request_is_seen_by_chain_and_hr(): void
    {
        $letter = $this->staffRequest('staff.ee@edocs.test', 'م ب ش');

        foreach (['staff.ee', 'eng.ee', 'eng.dean', 'academic', 'president', 'hr', 'registrar'] as $who) {
            $this->assertTrue($this->sees("$who@edocs.test", $letter), "$who should see");
        }
        foreach (['eng.civil', 'med.dean', 'finance', 'staff.eng', 'admin'] as $who) {
            $this->assertFalse($this->sees("$who@edocs.test", $letter), "$who should not see");
        }
    }

    public function test_confidential_letter_hides_from_ancestors(): void
    {
        $letter = $this->staffRequest('staff.ee@edocs.test', 'م ب ش', confidential: true);

        $this->assertTrue($this->sees('staff.ee@edocs.test', $letter));
        $this->assertTrue($this->sees('eng.ee@edocs.test', $letter));   // المدير المباشر
        $this->assertTrue($this->sees('hr@edocs.test', $letter));       // المستلم
        $this->assertFalse($this->sees('eng.dean@edocs.test', $letter)); // جد
        $this->assertFalse($this->sees('president@edocs.test', $letter));
        $this->assertFalse($this->sees('registrar@edocs.test', $letter));
    }

    public function test_drafts_are_hidden_from_recipients_and_ancestors(): void
    {
        $letter = $this->unitLetter('ه ك', ['م ب ش'], status: LetterStatus::Draft);

        $this->assertTrue($this->sees('eng.ee@edocs.test', $letter));
        $this->assertFalse($this->sees('hr@edocs.test', $letter));
        $this->assertFalse($this->sees('eng.dean@edocs.test', $letter));
    }

    public function test_visibility_follows_the_current_assignment(): void
    {
        $letter = $this->unitLetter('ك ط', ['ش م']);
        $newDean = User::factory()->create();

        $this->assertFalse($letter->isVisibleTo($newDean));

        // نقل: إنهاء تكليف العميد القديم وتكليف الجديد
        UnitAssignment::where('unit_id', $this->unit('ك ط')->id)->update(['end_date' => now()->subDay()]);
        UnitAssignment::create([
            'unit_id' => $this->unit('ك ط')->id, 'user_id' => $newDean->id,
            'kind' => AssignmentKind::Original, 'start_date' => now(),
        ]);

        $this->assertTrue($letter->isVisibleTo($newDean->fresh()));
        $this->assertFalse($this->sees('med.dean@edocs.test', $letter)); // فقد الرؤية بعد النقل
        $this->assertSame($newDean->id, $this->unit('ك ط')->currentHolder()->id);
    }

    public function test_broadcast_scopes(): void
    {
        $make = function (array $target) {
            $root = $this->unit('م ج');
            $l = Letter::create([
                'type' => LetterType::Broadcast, 'subject' => 'تعميم',
                'sender_unit_id' => $root->id, 'origin_unit_id' => $root->id,
                'sender_user_id' => $root->currentHolder()->id, 'status' => LetterStatus::Sent,
            ]);
            $l->broadcastTargets()->create($target);

            return $l;
        };

        $colleges = $make(['scope' => BroadcastScope::UnitType, 'unit_type' => UnitType::College]);
        $this->assertTrue($this->sees('eng.dean@edocs.test', $colleges));
        $this->assertTrue($this->sees('staff.med@edocs.test', $colleges)); // موظف في كلية
        $this->assertFalse($this->sees('hr@edocs.test', $colleges));

        $engBranch = $make(['scope' => BroadcastScope::Subtree, 'root_unit_id' => $this->unit('ك ه')->id]);
        $this->assertTrue($this->sees('eng.ee@edocs.test', $engBranch));
        $this->assertTrue($this->sees('staff.ee@edocs.test', $engBranch));
        $this->assertFalse($this->sees('med.dean@edocs.test', $engBranch));

        $allStaff = $make(['scope' => BroadcastScope::AllStaff]);
        $this->assertTrue($this->sees('staff.hr@edocs.test', $allStaff));

        $allUnits = $make(['scope' => BroadcastScope::AllUnits]);
        $this->assertTrue($this->sees('eng.civil@edocs.test', $allUnits));
        $this->assertFalse($this->sees('staff.hr@edocs.test', $allUnits));
    }

    public function test_visible_to_scope_lists_only_allowed_letters(): void
    {
        $this->unitLetter('ك ط', ['ش م']);
        $this->staffRequest('staff.ee@edocs.test', 'م ب ش');
        $this->unitLetter('ك ه', ['م ج']);

        $this->assertSame(2, Letter::visibleTo($this->user('eng.dean@edocs.test'))->count());
        $this->assertSame(3, Letter::visibleTo($this->user('president@edocs.test'))->count());
        $this->assertSame(0, Letter::visibleTo($this->user('admin@edocs.test'))->count());
    }

    public function test_numbering_is_sequential_per_unit_and_year(): void
    {
        $hr = $this->unit('م ب ش');

        $a = Numbering::next($hr, NumberDirection::Outgoing, 2026);
        $b = Numbering::next($hr, NumberDirection::Outgoing, 2026);
        $in = Numbering::next($hr, NumberDirection::Incoming, 2026);

        $this->assertSame('جك/م ب ش/2026/0001', $a['number']);
        $this->assertSame(2, $b['seq']);
        $this->assertSame('جك/م ب ش/و/2026/0001', $in['number']);
    }
}
