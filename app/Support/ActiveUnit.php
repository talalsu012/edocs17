<?php

namespace App\Support;

use App\Models\Unit;
use App\Models\User;

/**
 * الجهة التي يعمل باسمها المستخدم الآن (إن كان مكلّفًا بأكثر من جهة، مثل الإنابة).
 */
class ActiveUnit
{
    public static function get(?User $user = null): ?Unit
    {
        $user ??= auth()->user();
        if (! $user) {
            return null;
        }

        $units = $user->currentUnits();
        $id = session('active_unit_id');

        return $units->firstWhere('id', $id) ?? $units->first();
    }

    public static function set(User $user, int $unitId): void
    {
        if ($user->holdsUnit($unitId)) {
            session(['active_unit_id' => $unitId]);
        }
    }
}
