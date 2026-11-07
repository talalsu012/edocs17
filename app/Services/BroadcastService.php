<?php

namespace App\Services;

use App\Enums\BroadcastScope;
use App\Enums\LetterType;
use App\Models\BroadcastRead;
use App\Models\BroadcastTarget;
use App\Models\Letter;
use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\Support\ActiveUnit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

/**
 * التعاميم: الصلاحية، الجمهور المستهدف، «علمت» ونسبة الاطلاع.
 * الجمهور يُحسب بنفس منطق الرؤية في LetterVisibility حتى تتطابق النسبة مع من يرى التعميم فعلًا.
 */
class BroadcastService
{
    /**
     * هل يحق لجهة إصدار تعميم بهذا النطاق؟
     * - أي جهة: تعميم على فرعها (الجهة وما تحتها).
     * - الجهات المخوّلة (can_broadcast_all): كل النطاقات.
     */
    public function canBroadcast(Unit $unit, BroadcastScope $scope, ?Unit $root = null): bool
    {
        if ($unit->can_broadcast_all) {
            return true;
        }

        return $scope === BroadcastScope::Subtree
            && $root !== null
            && str_starts_with($root->path, $unit->path);
    }

    /** النطاقات المتاحة لجهة. */
    public function allowedScopes(Unit $unit): array
    {
        return $unit->can_broadcast_all ? BroadcastScope::cases() : [BroadcastScope::Subtree];
    }

    /** المستخدمون المستهدفون بالتعميم. */
    public function audience(Letter $letter): Builder
    {
        $target = $letter->broadcastTargets->first();
        if (! $target) {
            return User::query()->whereRaw('1 = 0');
        }

        $active = fn () => UnitAssignment::query()->active()->select('user_id');
        $q = User::query()->where('is_active', true);

        return match ($target->scope) {
            BroadcastScope::AllStaff => $q,

            BroadcastScope::AllUnits => $q->whereIn('id', $active()),

            BroadcastScope::Subtree => $q->where(function (Builder $w) use ($target, $active) {
                $path = $target->rootUnit->path;
                $w->whereIn('id', $active()->whereHas('unit', fn ($u) => $u->where('path', 'like', $path.'%')))
                    ->orWhereHas('homeUnit', fn ($u) => $u->where('path', 'like', $path.'%'));
            }),

            BroadcastScope::UnitType => $q->where(function (Builder $w) use ($target, $active) {
                $type = $target->unit_type->value;
                $w->whereIn('id', $active()->whereHas('unit', fn ($u) => $u->where('type', $type)))
                    ->orWhereHas('homeUnit', fn ($u) => $u->where('type', $type));
            }),
        };
    }

    public function isInAudience(User $user, Letter $letter): bool
    {
        return $this->audience($letter)->whereKey($user->id)->exists();
    }

    public function hasAcknowledged(User $user, Letter $letter): bool
    {
        return BroadcastRead::where('letter_id', $letter->id)->where('user_id', $user->id)->exists();
    }

    /** «علمت» */
    public function acknowledge(User $user, Letter $letter): void
    {
        if ($letter->type !== LetterType::Broadcast || ! $letter->isSent() || ! $this->isInAudience($user, $letter)) {
            throw new AuthorizationException('هذا التعميم غير موجه إليك.');
        }

        BroadcastRead::firstOrCreate(
            ['letter_id' => $letter->id, 'user_id' => $user->id],
            ['unit_id' => ActiveUnit::get($user)?->id, 'read_at' => now()],
        );
    }

    /** @return array{read:int, total:int, percent:int} */
    public function stats(Letter $letter): array
    {
        $total = $this->audience($letter)->count();
        $read = BroadcastRead::where('letter_id', $letter->id)
            ->whereIn('user_id', $this->audience($letter)->select('id'))
            ->count();

        return ['read' => $read, 'total' => $total, 'percent' => $total ? (int) round($read * 100 / $total) : 0];
    }

    /** وصف النطاق للعرض: «كل الكليات»، «فرع: كلية الهندسة»... */
    public function describe(BroadcastTarget $t): string
    {
        return match ($t->scope) {
            BroadcastScope::AllStaff => 'كل الموظفين',
            BroadcastScope::AllUnits => 'كل الجهات',
            BroadcastScope::Subtree => 'فرع: '.$t->rootUnit?->name,
            BroadcastScope::UnitType => 'كل الجهات من نوع: '.$t->unit_type?->label(),
        };
    }
}
