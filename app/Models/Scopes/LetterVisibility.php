<?php

namespace App\Models\Scopes;

use App\Enums\BroadcastScope;
use App\Enums\LetterStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * قواعد الرؤية الهرمية (القسم 4 من التحليل).
 *
 * المستخدم يرى الخطاب إذا تحقق أحد الشروط:
 *  1. هو كاتب الطلب الشخصي أو مستلمه الشخصي (خطابات الجهة تبقى للجهة لا لكاتبها).
 *  2. خطاب لم يُرسل بعد (مسودة/بانتظار الاعتماد) وهو يمثل الجهة المرسلة.
 *  3. خطاب مرسل غير سري: جهة المنشأ أو إحدى الجهات المستلمة/المحال إليها
 *     تقع داخل فرع إحدى جهاته الحالية (الجهة نفسها أو ما تحتها). الرؤية تنزل ولا تصعد ولا تمتد أفقيًا.
 *     جهة المنشأ في طلب الموظف = جهة عمله، فيراه مديره المباشر ومن فوقه.
 *  4. خطاب مرسل سري: يراه فقط من يمثل جهة المنشأ (المرسل/المدير المباشر) أو جهة مستلمة بعينها، دون الأجداد.
 *  5. تعميم مرسل موجه للكل، أو لفرع يقع فيه، أو لنوع جهته.
 *
 * الرؤية مرتبطة بالجهات التي يشغلها المستخدم الآن (التكليفات السارية)، لا بشخصه.
 * مدير النظام لا يرى المحتوى بهذه الصفة.
 */
class LetterVisibility
{
    public static function apply(Builder $query, User $user): Builder
    {
        $unitIds = $user->currentUnitIds();
        $paths = $user->currentUnitPaths();
        $sentStates = [LetterStatus::Sent->value, LetterStatus::Cancelled->value];

        return $query->where(function (Builder $q) use ($user, $unitIds, $paths, $sentStates) {

            // (1) كاتب الطلب الشخصي — المراسلات الشخصية تنتقل مع الموظف،
            //     أما خطابات الجهة فتبقى للجهة ولا يراها كاتبها بعد نقله.
            $q->where(fn (Builder $w) => $w
                ->where('letters.sender_user_id', $user->id)
                ->whereNull('letters.sender_unit_id'));

            // (1) المستلم الشخصي لخطاب مرسل
            $q->orWhere(fn (Builder $w) => $w
                ->whereIn('letters.status', $sentStates)
                ->whereHas('recipients', fn (Builder $r) => $r->where('user_id', $user->id)));

            if ($unitIds) {
                // (2) مسودات وخطابات بانتظار الاعتماد لجهته
                $q->orWhere(fn (Builder $w) => $w
                    ->whereNotIn('letters.status', $sentStates)
                    ->whereIn('letters.sender_unit_id', $unitIds));

                // (3) خطاب مرسل غير سري — رؤية هرمية نزولًا
                $q->orWhere(fn (Builder $w) => $w
                    ->whereIn('letters.status', $sentStates)
                    ->where('letters.is_confidential', false)
                    ->where(fn (Builder $h) => $h
                        ->whereHas('originUnit', fn (Builder $u) => self::inSubtrees($u, $paths))
                        ->orWhereHas('recipients.unit', fn (Builder $u) => self::inSubtrees($u, $paths))));

                // (4) خطاب مرسل سري — الجهات المعنية مباشرة فقط
                $q->orWhere(fn (Builder $w) => $w
                    ->whereIn('letters.status', $sentStates)
                    ->where('letters.is_confidential', true)
                    ->where(fn (Builder $c) => $c
                        ->whereIn('letters.origin_unit_id', $unitIds)
                        ->orWhereIn('letters.sender_unit_id', $unitIds)
                        ->orWhereHas('recipients', fn (Builder $r) => $r->whereIn('unit_id', $unitIds))));
            }

            // (5) التعاميم
            $q->orWhere(fn (Builder $w) => $w
                ->whereIn('letters.status', $sentStates)
                ->whereHas('broadcastTargets', fn (Builder $b) => self::broadcastMatches($b, $user, (bool) $unitIds)));
        });
    }

    /** الجهة تقع داخل فرع إحدى المسارات (path يبدأ بأحدها). */
    protected static function inSubtrees(Builder $units, array $paths): void
    {
        $units->where(function (Builder $w) use ($paths) {
            foreach ($paths as $path) {
                $w->orWhere('units.path', 'like', $path.'%');
            }
        });
    }

    protected static function broadcastMatches(Builder $b, User $user, bool $isUnitUser): void
    {
        $chain = $user->ancestorPathsForBroadcast();
        $types = $user->unitTypesForBroadcast();

        $b->where(function (Builder $w) use ($chain, $types, $isUnitUser) {
            // كل الموظفين: يصل للجميع
            $w->where('scope', BroadcastScope::AllStaff->value);

            // كل الجهات: يصل لمستخدمي الجهات
            if ($isUnitUser) {
                $w->orWhere('scope', BroadcastScope::AllUnits->value);
            }

            // فرع من الشجرة: جذر الفرع أحد أجداد جهته (أو جهته نفسها)
            if ($chain) {
                $w->orWhere(fn (Builder $s) => $s
                    ->where('scope', BroadcastScope::Subtree->value)
                    ->whereHas('rootUnit', fn (Builder $u) => $u->whereIn('path', $chain)));
            }

            // نوع جهات (مثل كل الكليات)
            if ($types) {
                $w->orWhere(fn (Builder $s) => $s
                    ->where('scope', BroadcastScope::UnitType->value)
                    ->whereIn('unit_type', $types));
            }
        });
    }
}
