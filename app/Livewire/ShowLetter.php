<?php

namespace App\Livewire;

use App\Enums\LetterStatus;
use App\Enums\RecipientStatus;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Unit;
use App\Services\BroadcastService;
use App\Services\LetterService;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ShowLetter extends Component
{
    #[Locked]
    public int $letterId;

    public string $directive = '';

    public string $cancelReason = '';

    public string $statusNote = '';

    public ?int $referUnitId = null;

    public string $referNote = 'للاطلاع والإجراء';

    public function mount(Letter $letter, LetterService $service): void
    {
        $user = auth()->user();
        abort_unless($letter->isVisibleTo($user), 403, 'لا تملك صلاحية الاطلاع على هذا الخطاب.');

        if ($service->canEditDraft($user, $letter)) {
            $this->redirectRoute('letters.edit', $letter, navigate: true);

            return;
        }

        $this->letterId = $letter->id;
        $service->open($user, $letter);

        // فتح الخطاب يعلّم تنبيهاته كمقروءة
        $user->unreadNotifications()->where('data', 'like', '%"letter_id":'.$letter->id.',%')->update(['read_at' => now()]);
    }

    protected function letter(): Letter
    {
        return Letter::with([
            'senderUnit', 'originUnit', 'sender', 'approver', 'canceller', 'parent', 'replies',
            'recipients.unit', 'recipients.user', 'recipients.receiver', 'recipients.referredFrom.unit', 'attachments',
            'broadcastTargets.rootUnit',
            'events.actor', 'events.actorUnit',
        ])->findOrFail($this->letterId);
    }

    public function setStatus(int $recipientId, string $status, LetterService $service): void
    {
        $this->resetErrorBag();
        $row = LetterRecipient::where('letter_id', $this->letterId)->findOrFail($recipientId);
        $service->setRecipientStatus(auth()->user(), $row, RecipientStatus::from($status), $this->statusNote ?: null);
        $this->statusNote = '';
        session()->flash('status', 'تم تحديث الحالة إلى «'.RecipientStatus::from($status)->label().'».');
    }

    public function acknowledge(BroadcastService $broadcasts): void
    {
        $broadcasts->acknowledge(auth()->user(), $this->letter());
        session()->flash('status', 'تم تسجيل اطلاعك على التعميم.');
    }

    public function refer(int $recipientId, LetterService $service): void
    {
        $this->resetErrorBag();
        $this->validate(['referUnitId' => 'required|integer', 'referNote' => 'nullable|string|max:500'], [], [
            'referUnitId' => 'الجهة المحال إليها',
        ]);
        $row = LetterRecipient::where('letter_id', $this->letterId)->findOrFail($recipientId);
        $new = $service->refer(auth()->user(), $row, $this->referUnitId, $this->referNote);
        $this->referUnitId = null;
        session()->flash('status', 'تمت الإحالة إلى '.$new->unit->name.'.');
    }

    public function addDirective(LetterService $service): void
    {
        $this->resetErrorBag();
        $service->addDirective(auth()->user(), $this->letter(), $this->directive);
        $this->directive = '';
        session()->flash('status', 'تمت إضافة التوجيه.');
    }

    public function cancel(LetterService $service): void
    {
        $this->resetErrorBag();
        $service->cancel(auth()->user(), $this->letter(), $this->cancelReason);
        $this->cancelReason = '';
        session()->flash('status', 'تم إلغاء الخطاب.');
    }

    public function render(LetterService $service, BroadcastService $broadcasts)
    {
        $letter = $this->letter();
        $user = auth()->user();
        $myRows = $service->myRecipientRows($user, $letter);

        $broadcast = null;
        if ($letter->isBroadcast() && $letter->isSent()) {
            $target = $letter->broadcastTargets->first();
            $broadcast = [
                'target' => $target ? $broadcasts->describe($target) : null,
                'inAudience' => $broadcasts->isInAudience($user, $letter),
                'acknowledged' => $broadcasts->hasAcknowledged($user, $letter),
                // نسبة الاطلاع تظهر للجهة المرسلة ومن فوقها
                'stats' => $user->isUnitUser() && $letter->originUnit
                    && collect($user->currentUnits())->contains(fn ($u) => str_starts_with($letter->originUnit->path, $u->path))
                    ? $broadcasts->stats($letter) : null,
            ];
        }

        // جهات الإحالة: الجهات التابعة لجهتي أولًا ثم بقية الجهات
        $referOptions = collect();
        if ($myRows->isNotEmpty() && $letter->status === LetterStatus::Sent) {
            $mine = $myRows->pluck('unit')->filter()->first();
            $existing = $letter->recipients->pluck('unit_id')->filter()->all();
            $all = Unit::active()->orderBy('path')->get(['id', 'name', 'path', 'depth'])
                ->reject(fn ($u) => in_array($u->id, $existing, true));
            $referOptions = $mine
                ? $all->partition(fn ($u) => str_starts_with($u->path, $mine->path))
                : collect([collect(), $all]);
        }

        return view('livewire.show-letter', [
            'letter' => $letter,
            'myRows' => $myRows,
            'broadcast' => $broadcast,
            'referOptions' => $referOptions,
            'canCancel' => $letter->status === LetterStatus::Sent
                && ($letter->sender_unit_id ? $user->holdsUnit($letter->sender_unit_id) : $letter->sender_user_id === $user->id),
            'canReply' => $myRows->isNotEmpty() && $user->isUnitUser() && ! $letter->isBroadcast(),
            'canDirect' => $user->isUnitUser(),
        ])->title($letter->subject);
    }
}
