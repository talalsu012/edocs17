<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** جهة يحق للموظف العادي مراسلتها. */
#[Fillable(['unit_id'])]
class StaffAllowedTarget extends Model
{
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
