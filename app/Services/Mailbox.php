<?php

namespace App\Services;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\RecipientStatus;
use App\Models\Letter;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * استعلامات الصناديق (مو6). كل صندوق يمر أيضًا عبر visibleTo كطبقة أمان ثانية.
 */
class Mailbox
{
    public const BOXES = [
        'inbox' => 'الوارد',
        'outbox' => 'الصادر',
        'drafts' => 'المسودات',
        'broadcasts' => 'التعاميم',
        'following' => 'المتابعة',
        'archive' => 'الأرشيف',
    ];

    public function query(User $user, string $box, ?Unit $unit, string $search = ''): Builder
    {
        $sent = [LetterStatus::Sent->value, LetterStatus::Cancelled->value];
        $q = Letter::query()->visibleTo($user)
            ->with(['senderUnit', 'originUnit', 'sender', 'recipients.unit']);

        $unitId = $unit?->id;
        $mine = fn (Builder $r) => $r->where(fn (Builder $w) => $w
            ->when($unitId, fn ($x) => $x->where('unit_id', $unitId))
            ->orWhere('user_id', $user->id));

        match ($box) {
            'inbox' => $q->whereIn('status', $sent)
                ->where('type', '!=', LetterType::Broadcast->value)
                ->whereHas('recipients', fn (Builder $r) => $mine($r)
                    ->where('status', '!=', RecipientStatus::Archived->value)),

            'outbox' => $q->whereIn('status', $sent)
                ->where(fn (Builder $w) => $w
                    ->when($unitId, fn ($x) => $x->where('sender_unit_id', $unitId))
                    ->orWhere(fn ($x) => $x->whereNull('sender_unit_id')->where('sender_user_id', $user->id))),

            'drafts' => $q->whereNotIn('status', $sent)
                ->where(fn (Builder $w) => $w
                    ->when($unitId, fn ($x) => $x->where('sender_unit_id', $unitId))
                    ->orWhere(fn ($x) => $x->whereNull('sender_unit_id')->where('sender_user_id', $user->id))),

            'broadcasts' => $q->whereIn('status', $sent)->where('type', LetterType::Broadcast->value),

            // مراسلات الجهات التابعة وموظفيها (ومنها طلبات موظفي جهتي): مرئية لي لكن جهتي ليست طرفًا مباشرًا فيها
            'following' => $q->whereIn('status', $sent)
                ->where('type', '!=', LetterType::Broadcast->value)
                ->when($unitId, fn ($x) => $x
                    ->where(fn ($w) => $w->whereNull('sender_unit_id')->orWhere('sender_unit_id', '!=', $unitId))
                    ->whereDoesntHave('recipients', fn (Builder $r) => $r->where('unit_id', $unitId)))
                ->where('sender_user_id', '!=', $user->id)
                ->whereDoesntHave('recipients', fn (Builder $r) => $r->where('user_id', $user->id)),

            'archive' => $q->whereIn('status', $sent)
                ->whereHas('recipients', fn (Builder $r) => $mine($r)
                    ->where('status', RecipientStatus::Archived->value)),

            default => $q->whereRaw('1 = 0'),
        };

        if ($search = trim($search)) {
            $q->where(fn (Builder $w) => $w
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('outgoing_no', 'like', "%{$search}%")
                ->orWhereHas('recipients', fn ($r) => $r->where('incoming_no', 'like', "%{$search}%")));
        }

        return $q->orderByDesc($box === 'drafts' ? 'updated_at' : 'sent_at')->orderByDesc('id');
    }

    /** أعداد غير المستلم في الوارد لكل جهة — للشارة في القائمة الجانبية. */
    public function unreadCount(User $user, ?Unit $unit): int
    {
        return $this->query($user, 'inbox', $unit)
            ->whereHas('recipients', fn (Builder $r) => $r
                ->where(fn ($w) => $w->when($unit, fn ($x) => $x->where('unit_id', $unit->id))->orWhere('user_id', $user->id))
                ->where('status', RecipientStatus::Pending->value))
            ->count();
    }
}
