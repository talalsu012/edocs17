<?php

namespace App\Services;

use App\Enums\AssignmentKind;
use App\Enums\LetterStatus;
use App\Enums\RecipientStatus;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * التكليفات والتنقلات (القسم 8): الصندوق ملك الجهة، والتكليف يحدد من يملك مفتاحه.
 */
class AssignmentService
{
    /**
     * تكليف مستخدم بجهة.
     * - أصيل: يُنهى تكليف الأصيل السابق للجهة تلقائيًا (يوم قبل البداية)،
     *   ويُنهى اختياريًا أي تكليف أصيل آخر للمستخدم نفسه (النقل).
     * - إنابة: يتطلب تاريخ نهاية، ويتقدم على الأصيل خلال فترته ثم ينتهي تلقائيًا.
     */
    public function assign(User $admin, Unit $unit, User $user, array $data): UnitAssignment
    {
        $kind = AssignmentKind::from($data['kind'] ?? 'original');
        $start = Carbon::parse($data['start_date'] ?? today())->startOfDay();
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date'])->startOfDay() : null;

        if ($kind === AssignmentKind::Acting && ! $end) {
            throw ValidationException::withMessages(['endDate' => 'الإنابة تتطلب تاريخ نهاية.']);
        }
        if ($end && $end->lt($start)) {
            throw ValidationException::withMessages(['endDate' => 'تاريخ النهاية قبل تاريخ البداية.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['userId' => 'الحساب غير نشط.']);
        }

        return DB::transaction(function () use ($admin, $unit, $user, $data, $kind, $start, $end) {
            if ($kind === AssignmentKind::Original) {
                // إنهاء الأصيل الحالي للجهة
                $unit->assignments()->where('kind', AssignmentKind::Original->value)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start->toDateString()))
                    ->whereDate('start_date', '<', $start->toDateString())
                    ->update(['end_date' => $start->copy()->subDay()->toDateString()]);

                // النقل: إنهاء تكليفات المستخدم الأصيلة الأخرى
                if ($data['end_other_assignments'] ?? true) {
                    $user->assignments()->where('kind', AssignmentKind::Original->value)
                        ->where('unit_id', '!=', $unit->id)
                        ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $start->toDateString()))
                        ->whereDate('start_date', '<', $start->toDateString())
                        ->update(['end_date' => $start->copy()->subDay()->toDateString()]);
                }

                // تكليف بنفس تاريخ البداية يُستبدل
                $unit->assignments()->where('kind', AssignmentKind::Original->value)
                    ->whereDate('start_date', $start->toDateString())->delete();
            }

            $assignment = UnitAssignment::create([
                'unit_id' => $unit->id,
                'user_id' => $user->id,
                'kind' => $kind,
                'start_date' => $start,
                'end_date' => $end,
                'decision_no' => $data['decision_no'] ?? null,
                'decision_date' => $data['decision_date'] ?? null,
                'assigned_by' => $admin->id,
                'notes' => $data['notes'] ?? null,
            ]);

            $user->forgetCurrentUnits();

            return $assignment;
        });
    }

    /** إنهاء تكليف بتاريخ (اليوم افتراضيًا: يبقى ساريًا حتى نهاية أمس). */
    public function end(UnitAssignment $assignment, ?string $date = null): void
    {
        $end = $date ? Carbon::parse($date) : today()->subDay();
        if ($end->lt($assignment->start_date)) {
            $end = $assignment->start_date->copy();
        }
        $assignment->update(['end_date' => $end->toDateString()]);
    }

    /**
     * تقرير التسليم والتسلّم: المعلّقات والمتأخرات والمسودات.
     *
     * @return array{pending:int, in_progress:int, overdue:int, drafts:int, overdue_list:Collection}
     */
    public function handover(Unit $unit): array
    {
        $open = LetterRecipient::query()->where('unit_id', $unit->id)
            ->whereIn('status', [RecipientStatus::Pending->value, RecipientStatus::Received->value, RecipientStatus::InProgress->value])
            ->whereHas('letter', fn ($q) => $q->where('status', LetterStatus::Sent->value));

        $overdue = (clone $open)->whereHas('letter', fn ($q) => $q->whereDate('due_date', '<', today()))
            ->with('letter.senderUnit', 'letter.originUnit')->get();

        return [
            'pending' => (clone $open)->whereIn('status', [RecipientStatus::Pending->value, RecipientStatus::Received->value])->count(),
            'in_progress' => (clone $open)->where('status', RecipientStatus::InProgress->value)->count(),
            'overdue' => $overdue->count(),
            'drafts' => Letter::where('sender_unit_id', $unit->id)->whereNotIn('status', [LetterStatus::Sent->value, LetterStatus::Cancelled->value])->count(),
            'overdue_list' => $overdue,
        ];
    }

    /** الجهات النشطة التي لا يشغلها أحد الآن. */
    public function vacantUnits()
    {
        return Unit::active()->whereDoesntHave('assignments', fn ($q) => $q->active())->orderBy('path')->get();
    }
}
