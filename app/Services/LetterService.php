<?php

namespace App\Services;

use App\Enums\BroadcastScope;
use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\NumberDirection;
use App\Enums\Priority;
use App\Enums\RecipientRole;
use App\Enums\RecipientStatus;
use App\Enums\UnitType;
use App\Models\Letter;
use App\Models\LetterEvent;
use App\Models\LetterRecipient;
use App\Models\Numbering;
use App\Models\StaffAllowedTarget;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دورة حياة الخطاب: حفظ المسودة، الإرسال، الاستلام، تغيير الحالة، الإلغاء، التوجيه.
 * كل التحقق من الصلاحيات يتم هنا (في الخادم) لا في الواجهة.
 */
class LetterService
{
    /**
     * إنشاء أو تحديث مسودة.
     *
     * @param  array{mode:string, unit_id?:int|null, subject:string, body?:string|null, priority?:string,
     *               is_confidential?:bool, due_date?:string|null, to:array<int>, cc?:array<int>,
     *               template_id?:int|null, parent_letter_id?:int|null}  $data
     * @param  array<UploadedFile>  $files
     */
    public function saveDraft(User $user, array $data, ?Letter $letter = null, array $files = []): Letter
    {
        $personal = ($data['mode'] ?? 'unit') === 'personal';
        $broadcast = ($data['mode'] ?? 'unit') === 'broadcast';
        $to = array_values(array_unique(array_map('intval', $data['to'] ?? [])));
        $cc = array_values(array_diff(array_unique(array_map('intval', $data['cc'] ?? [])), $to));

        if ($personal) {
            $origin = $user->homeUnit ?? throw ValidationException::withMessages(['to' => 'لا توجد جهة عمل مسجلة لك؛ راجع الموارد البشرية.']);
            $allowed = StaffAllowedTarget::pluck('unit_id')->all();
            if (array_diff($to, $allowed) || $cc) {
                throw ValidationException::withMessages(['to' => 'يمكن للطلب الشخصي أن يُوجَّه فقط للجهات المسموح بها (الموارد البشرية، الشؤون المالية...).']);
            }
            $senderUnit = null;
        } else {
            $senderUnit = Unit::find($data['unit_id'] ?? null);
            if (! $senderUnit || ! $user->holdsUnit($senderUnit)) {
                throw new AuthorizationException('لا تملك صلاحية الإرسال باسم هذه الجهة.');
            }
            $origin = $senderUnit;
            if ($broadcast) {
                $to = $cc = [];
                $target = $this->broadcastTarget($senderUnit, $data['broadcast'] ?? []);
            }
            if (in_array($senderUnit->id, [...$to, ...$cc], true)) {
                throw ValidationException::withMessages(['to' => 'لا يمكن إرسال الخطاب إلى الجهة نفسها.']);
            }
        }

        if ($letter && (! $this->canEditDraft($user, $letter))) {
            throw new AuthorizationException('لا يمكن تعديل هذا الخطاب.');
        }

        return DB::transaction(function () use ($user, $data, $letter, $files, $personal, $broadcast, $senderUnit, $origin, $to, $cc) {
            $isNew = ! $letter;
            $letter ??= new Letter(['status' => LetterStatus::Draft, 'sender_user_id' => $user->id]);

            $letter->fill([
                'type' => match (true) {
                    $personal => LetterType::StaffRequest,
                    $broadcast => LetterType::Broadcast,
                    (bool) ($data['parent_letter_id'] ?? null) => LetterType::Reply,
                    count($to) + count($cc) > 1 => LetterType::Multi,
                    default => LetterType::Direct,
                },
                'subject' => $data['subject'],
                'body' => $data['body'] ?? null,
                'priority' => $data['priority'] ?? Priority::Normal->value,
                'is_confidential' => ! $broadcast && (bool) ($data['is_confidential'] ?? false),
                'due_date' => $broadcast ? null : (($data['due_date'] ?? null) ?: null),
                'sender_unit_id' => $senderUnit?->id,
                'origin_unit_id' => $origin->id,
                'template_id' => $data['template_id'] ?? null,
                'parent_letter_id' => $data['parent_letter_id'] ?? null,
                'is_acting' => $senderUnit ? $this->isActingFor($user, $senderUnit) : false,
            ]);
            $letter->save();

            // المستلمون: إعادة بناء القائمة في المسودة
            $letter->recipients()->delete();
            foreach ($to as $id) {
                $letter->recipients()->create(['unit_id' => $id, 'role' => RecipientRole::To]);
            }
            foreach ($cc as $id) {
                $letter->recipients()->create(['unit_id' => $id, 'role' => RecipientRole::Cc]);
            }

            // أهداف التعميم
            $letter->broadcastTargets()->delete();
            if ($broadcast) {
                $letter->broadcastTargets()->create($this->broadcastTarget($senderUnit, $data['broadcast'] ?? []));
            }

            foreach ($files as $file) {
                $path = $file->store("letters/{$letter->id}", 'local');
                $letter->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'mime_type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                    'uploaded_by' => $user->id,
                ]);
            }

            if ($isNew) {
                $letter->logEvent(LetterEvent::CREATED, $user, ['to_status' => LetterStatus::Draft->value]);
            }

            return $letter->fresh(['recipients']);
        });
    }

    /** إرسال المسودة: رقم صادر + ظهورها في وارد كل مستلم. */
    public function send(User $user, Letter $letter): Letter
    {
        if (! $this->canEditDraft($user, $letter)) {
            throw new AuthorizationException('لا يمكن إرسال هذا الخطاب.');
        }
        if ($letter->recipients()->doesntExist() && $letter->broadcastTargets()->doesntExist()) {
            throw ValidationException::withMessages(['to' => 'اختر جهة مستلمة واحدة على الأقل.']);
        }

        return DB::transaction(function () use ($user, $letter) {
            $numberUnit = $letter->senderUnit ?? $letter->originUnit;
            $no = Numbering::next($numberUnit, NumberDirection::Outgoing);

            $from = $letter->status->value;
            $letter->forceFill([
                'outgoing_no' => $no['number'],
                'outgoing_year' => $no['year'],
                'outgoing_seq' => $no['seq'],
                'status' => LetterStatus::Sent,
                'sent_at' => now(),
                'approver_user_id' => $letter->sender_unit_id ? $user->id : null,
                'approved_at' => $letter->sender_unit_id ? now() : null,
            ])->save();

            $letter->recipients()->update(['status' => RecipientStatus::Pending->value]);

            $letter->logEvent(LetterEvent::SENT, $user, [
                'from_status' => $from, 'to_status' => LetterStatus::Sent->value,
                'meta' => ['outgoing_no' => $no['number']],
            ]);

            $sent = $letter->fresh();
            DB::afterCommit(fn () => app(Notifier::class)->letterSent($sent, $user));

            return $sent;
        });
    }

    /** يُستدعى عند فتح الخطاب: تسجيل الاطلاع، والاستلام الآلي برقم وارد إن كان المستخدم يمثل جهة مستلمة. */
    public function open(User $user, Letter $letter): void
    {
        $letter->logEvent(LetterEvent::VIEWED, $user);

        if (! $letter->isSent() || $letter->status === LetterStatus::Cancelled) {
            return;
        }

        foreach ($this->myRecipientRows($user, $letter) as $row) {
            if ($row->status === RecipientStatus::Pending) {
                $this->receive($user, $row);
            }
        }
    }

    public function receive(User $user, LetterRecipient $row): void
    {
        DB::transaction(function () use ($user, $row) {
            $no = $row->unit ? Numbering::next($row->unit, NumberDirection::Incoming) : null;

            $row->forceFill([
                'status' => RecipientStatus::Received,
                'received_at' => now(),
                'received_by' => $user->id,
                'incoming_no' => $no['number'] ?? null,
                'incoming_year' => $no['year'] ?? null,
                'incoming_seq' => $no['seq'] ?? null,
            ])->save();

            $row->letter->logEvent(LetterEvent::RECEIVED, $user, [
                'recipient_id' => $row->id,
                'from_status' => RecipientStatus::Pending->value,
                'to_status' => RecipientStatus::Received->value,
                'meta' => ['incoming_no' => $no['number'] ?? null],
            ]);
        });
    }

    /** تغيير حالة المستلم: قيد الإجراء / مُنجز / محفوظ. */
    public function setRecipientStatus(User $user, LetterRecipient $row, RecipientStatus $to, ?string $note = null): void
    {
        if (! $this->holdsRecipient($user, $row)) {
            throw new AuthorizationException('هذا الإجراء متاح للجهة المستلمة فقط.');
        }

        $allowed = match ($row->status) {
            RecipientStatus::Pending, RecipientStatus::Received => [RecipientStatus::InProgress, RecipientStatus::Done, RecipientStatus::Archived],
            RecipientStatus::InProgress => [RecipientStatus::Done, RecipientStatus::Archived],
            RecipientStatus::Done, RecipientStatus::Referred => [RecipientStatus::Archived],
            RecipientStatus::Archived => [RecipientStatus::Received],
        };
        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => 'انتقال غير مسموح من «'.$row->status->label().'» إلى «'.$to->label().'».']);
        }

        DB::transaction(function () use ($user, $row, $to, $note) {
            $from = $row->status;
            if ($from === RecipientStatus::Pending) {
                $this->receive($user, $row);
                $from = RecipientStatus::Received;
            }

            $row->forceFill(array_filter([
                'status' => $to,
                'done_at' => $to === RecipientStatus::Done ? now() : null,
                'done_by' => $to === RecipientStatus::Done ? $user->id : null,
                'archived_at' => $to === RecipientStatus::Archived ? now() : null,
            ], fn ($v) => $v !== null))->save();

            if ($to === RecipientStatus::Received) { // إخراج من الحفظ
                $row->forceFill(['archived_at' => null])->save();
            }

            $row->letter->logEvent(LetterEvent::STATUS_CHANGED, $user, [
                'recipient_id' => $row->id, 'from_status' => $from->value, 'to_status' => $to->value, 'note' => $note,
            ]);
        });
    }

    /** إلغاء خطاب مرسل (لا حذف) مع ذكر السبب — للجهة المرسلة فقط. */
    public function cancel(User $user, Letter $letter, string $reason): void
    {
        $owns = $letter->sender_unit_id ? $user->holdsUnit($letter->sender_unit_id) : $letter->sender_user_id === $user->id;
        if (! $owns || $letter->status !== LetterStatus::Sent) {
            throw new AuthorizationException('لا يمكن إلغاء هذا الخطاب.');
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['cancelReason' => 'اذكر سبب الإلغاء.']);
        }

        $letter->forceFill([
            'status' => LetterStatus::Cancelled, 'cancel_reason' => $reason,
            'cancelled_by' => $user->id, 'cancelled_at' => now(),
        ])->save();

        $letter->logEvent(LetterEvent::CANCELLED, $user, [
            'from_status' => LetterStatus::Sent->value, 'to_status' => LetterStatus::Cancelled->value, 'note' => $reason,
        ]);
    }

    /** توجيه/تعليق من جهة أعلى ترى الخطاب اطلاعًا (لا يغيّر الحالة). */
    public function addDirective(User $user, Letter $letter, string $note): void
    {
        if (! $letter->isVisibleTo($user) || ! $user->isUnitUser()) {
            throw new AuthorizationException('لا يمكنك إضافة توجيه على هذا الخطاب.');
        }
        if (mb_strlen(trim($note)) < 2) {
            throw ValidationException::withMessages(['directive' => 'اكتب نص التوجيه.']);
        }

        $letter->logEvent(LetterEvent::DIRECTIVE, $user, ['note' => trim($note)]);
        app(Notifier::class)->directive($letter, trim($note), $user);
    }

    public function discardDraft(User $user, Letter $letter): void
    {
        if (! $this->canEditDraft($user, $letter)) {
            throw new AuthorizationException('لا يمكن حذف هذا الخطاب.');
        }

        DB::transaction(function () use ($letter) {
            $letter->recipients()->delete();
            $letter->broadcastTargets()->delete();
            $letter->attachments()->delete();
            $letter->events()->getQuery()->toBase()->delete(); // سجل المسودة فقط؛ لم تُرسل بعد
            $letter->delete();
        });
    }

    /**
     * إحالة خطاب وارد إلى جهة أخرى مع توجيه («للإفادة»، «للاطلاع والإجراء»...).
     * صف المستلم الأصلي يصبح «محال»، ويُنشأ صف جديد للجهة المحال إليها.
     */
    public function refer(User $user, LetterRecipient $row, int $unitId, ?string $note): LetterRecipient
    {
        if (! $this->holdsRecipient($user, $row)) {
            throw new AuthorizationException('الإحالة متاحة للجهة المستلمة فقط.');
        }
        if ($row->letter->status !== LetterStatus::Sent) {
            throw ValidationException::withMessages(['referUnitId' => 'لا يمكن إحالة خطاب ملغى.']);
        }
        if (! in_array($row->status, [RecipientStatus::Pending, RecipientStatus::Received, RecipientStatus::InProgress], true)) {
            throw ValidationException::withMessages(['referUnitId' => 'لا يمكن إحالة خطاب بحالة «'.$row->status->label().'».']);
        }
        $target = Unit::active()->find($unitId);
        if (! $target || $target->id === $row->unit_id) {
            throw ValidationException::withMessages(['referUnitId' => 'اختر جهة صحيحة للإحالة.']);
        }
        if ($row->letter->recipients()->where('unit_id', $target->id)->whereNotIn('status', [RecipientStatus::Archived->value])->exists()) {
            throw ValidationException::withMessages(['referUnitId' => 'الخطاب موجود أصلًا لدى هذه الجهة.']);
        }

        return DB::transaction(function () use ($user, $row, $target, $note) {
            if ($row->status === RecipientStatus::Pending) {
                $this->receive($user, $row);
            }
            $from = $row->fresh()->status;

            $new = $row->letter->recipients()->create([
                'unit_id' => $target->id,
                'role' => RecipientRole::Referred,
                'referred_from_id' => $row->id,
                'referral_note' => $note ?: null,
                'status' => RecipientStatus::Pending,
            ]);

            $row->forceFill(['status' => RecipientStatus::Referred])->save();

            $row->letter->logEvent(LetterEvent::REFERRED, $user, [
                'recipient_id' => $row->id,
                'from_status' => $from->value,
                'to_status' => RecipientStatus::Referred->value,
                'note' => $note,
                'meta' => ['to_unit_id' => $target->id, 'to_unit' => $target->name, 'new_recipient_id' => $new->id],
            ]);

            DB::afterCommit(fn () => app(Notifier::class)->referred($new, $note, $user));

            return $new;
        });
    }

    /** يتحقق من نطاق التعميم وصلاحية الجهة ويعيد بيانات الهدف. */
    protected function broadcastTarget(Unit $senderUnit, array $b): array
    {
        $scope = BroadcastScope::tryFrom($b['scope'] ?? '') ?? BroadcastScope::Subtree;
        $root = $scope === BroadcastScope::Subtree ? Unit::find($b['root_unit_id'] ?? $senderUnit->id) : null;
        $type = $scope === BroadcastScope::UnitType ? UnitType::tryFrom($b['unit_type'] ?? '') : null;

        if ($scope === BroadcastScope::Subtree && ! $root) {
            throw ValidationException::withMessages(['broadcastRoot' => 'اختر الفرع المستهدف.']);
        }
        if ($scope === BroadcastScope::UnitType && ! $type) {
            throw ValidationException::withMessages(['broadcastType' => 'اختر نوع الجهات المستهدفة.']);
        }
        if (! app(BroadcastService::class)->canBroadcast($senderUnit, $scope, $root)) {
            throw new AuthorizationException('جهتك مخوّلة بالتعميم على فرعها فقط.');
        }

        return ['scope' => $scope, 'root_unit_id' => $root?->id, 'unit_type' => $type];
    }

    /* ---------------- صلاحيات مساعدة ---------------- */

    public function canEditDraft(User $user, Letter $letter): bool
    {
        if ($letter->isSent()) {
            return false;
        }

        return $letter->sender_unit_id
            ? $user->holdsUnit($letter->sender_unit_id)
            : $letter->sender_user_id === $user->id;
    }

    public function holdsRecipient(User $user, LetterRecipient $row): bool
    {
        return ($row->unit_id && $user->holdsUnit($row->unit_id)) || ($row->user_id && $row->user_id === $user->id);
    }

    /** @return Collection<int, LetterRecipient> */
    public function myRecipientRows(User $user, Letter $letter)
    {
        return $letter->recipients->filter(fn (LetterRecipient $r) => $this->holdsRecipient($user, $r))->values();
    }

    protected function isActingFor(User $user, Unit $unit): bool
    {
        return $user->activeAssignments()->where('unit_id', $unit->id)->where('kind', 'acting')->exists();
    }
}
