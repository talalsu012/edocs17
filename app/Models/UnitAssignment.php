<?php

namespace App\Models;

use App\Enums\AssignmentKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * تكليف شخص بتمثيل جهة لفترة محددة (أصيل أو بالإنابة).
 */
#[Fillable(['unit_id', 'user_id', 'kind', 'start_date', 'end_date', 'decision_no', 'decision_date', 'assigned_by', 'notes'])]
class UnitAssignment extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => AssignmentKind::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'decision_date' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** التكليفات السارية في تاريخ معيّن (اليوم افتراضيًا). */
    public function scopeActive(Builder $q, ?Carbon $on = null): void
    {
        $day = ($on ?? now())->toDateString();

        $q->whereDate('start_date', '<=', $day)
            ->where(fn (Builder $w) => $w->whereNull('end_date')->orWhereDate('end_date', '>=', $day));
    }

    public function isActive(?Carbon $on = null): bool
    {
        $on ??= now();

        return $this->start_date->lte($on) && ($this->end_date === null || $this->end_date->gte($on->copy()->startOfDay()));
    }

    public function isActing(): bool
    {
        return $this->kind === AssignmentKind::Acting;
    }
}
