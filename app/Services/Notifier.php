<?php

namespace App\Services;

use App\Enums\LetterStatus;
use App\Enums\RecipientRole;
use App\Enums\RecipientStatus;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Setting;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\LetterNotification as N;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * من يُنبَّه ومتى. التنبيه يذهب لشاغل الجهة وقت الحدث (التكليف الساري).
 */
class Notifier
{
    /** عند الإرسال: كل جهة مستلمة (وكل مستلم شخصي). التعاميم لا تُرسل تنبيهات فردية (لها شارة في صندوق التعاميم). */
    public function letterSent(Letter $letter, ?User $actor = null): void
    {
        if ($letter->isBroadcast()) {
            return;
        }

        $kind = $letter->parent_letter_id ? N::REPLY : N::NEW;
        foreach ($letter->recipients()->with('unit.activeAssignment.user', 'user')->get() as $row) {
            $this->send($this->usersFor($row)->reject(fn ($u) => $u->is($actor)), new N($letter, $kind, recipientId: $row->id));
        }
    }

    public function referred(LetterRecipient $newRow, ?string $note, ?User $actor = null): void
    {
        $this->send($this->usersFor($newRow)->reject(fn ($u) => $u->is($actor)), new N($newRow->letter, N::REFERRED, $note, $newRow->id));
    }

    /** التوجيه يصل للجهة المرسلة وللجهات المستلمة المطلوب منها إجراء. */
    public function directive(Letter $letter, string $note, User $actor): void
    {
        $users = $this->holdersOf($letter->senderUnit)
            ->merge($letter->recipients()->where('role', '!=', RecipientRole::Cc->value)->with('unit.activeAssignment.user', 'user')->get()
                ->flatMap(fn ($r) => $this->usersFor($r)))
            ->unique('id')->reject(fn ($u) => $u->is($actor));

        $this->send($users, new N($letter, N::DIRECTIVE, $note));
    }

    /**
     * التذكير اليومي: اقتراب موعد الرد، والتأخر (للمستلم وللجهة المرسلة). كل تذكير مرة واحدة لكل مستلم.
     *
     * @return array{due_soon:int, overdue:int}
     */
    public function dailyReminders(): array
    {
        $days = (int) Setting::get('reminder_days_before', 2);
        $open = fn () => LetterRecipient::query()
            ->where('role', '!=', RecipientRole::Cc->value)
            ->whereIn('status', [RecipientStatus::Pending->value, RecipientStatus::Received->value, RecipientStatus::InProgress->value])
            ->whereHas('letter', fn ($q) => $q->where('status', LetterStatus::Sent->value)->whereNotNull('due_date'))
            ->with(['letter.senderUnit.activeAssignment.user', 'letter.sender', 'unit.activeAssignment.user', 'user']);

        $stats = ['due_soon' => 0, 'overdue' => 0];

        $open()->whereNull('due_reminded_at')
            ->whereHas('letter', fn ($q) => $q->whereDate('due_date', '>=', today())->whereDate('due_date', '<=', today()->addDays($days)))
            ->chunkById(200, function ($rows) use (&$stats) {
                foreach ($rows as $row) {
                    $this->send($this->usersFor($row), new N($row->letter, N::DUE_SOON, recipientId: $row->id));
                    $row->forceFill(['due_reminded_at' => now()])->save();
                    $stats['due_soon']++;
                }
            });

        $open()->whereNull('overdue_notified_at')
            ->whereHas('letter', fn ($q) => $q->whereDate('due_date', '<', today()))
            ->chunkById(200, function ($rows) use (&$stats) {
                foreach ($rows as $row) {
                    $this->send($this->usersFor($row), new N($row->letter, N::OVERDUE, recipientId: $row->id));
                    $this->send($this->holdersOf($row->letter->senderUnit), new N($row->letter, N::OVERDUE_SENDER, $row->unit?->name, $row->id));
                    $row->forceFill(['overdue_notified_at' => now()])->save();
                    $stats['overdue']++;
                }
            });

        return $stats;
    }

    /** @return Collection<int, User> */
    protected function usersFor(LetterRecipient $row): Collection
    {
        return $row->user ? collect([$row->user]) : $this->holdersOf($row->unit);
    }

    /** @return Collection<int, User> */
    protected function holdersOf(?Unit $unit): Collection
    {
        $user = $unit?->activeAssignment?->user;

        return $user && $user->is_active ? collect([$user]) : collect();
    }

    protected function send(Collection $users, N $notification): void
    {
        if ($users->isNotEmpty()) {
            Notification::send($users->unique('id'), $notification);
        }
    }
}
