<?php

namespace App\Services;

use App\Enums\LetterStatus;
use App\Enums\RecipientRole;
use App\Enums\RecipientStatus;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Unit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * مؤشرات الأداء (مو14): أعداد فقط، بلا محتوى المراسلات.
 */
class Stats
{
    public const OPEN = ['pending', 'received', 'in_progress'];

    /**
     * صف لكل جهة: الجهة نفسها (بلا تابعيها) ثم كل جهة تابعة مباشرة (بفرعها كاملًا)، ثم الإجمالي.
     *
     * @return array{rows: Collection, total: array}
     */
    public function breakdown(Unit $unit, Carbon $from, Carbon $to): array
    {
        $rows = collect([['unit' => $unit, 'label' => $unit->name.' (مباشرة)', 'ids' => [$unit->id]]]);

        foreach ($unit->children()->where('is_active', true)->get() as $child) {
            $rows->push(['unit' => $child, 'label' => $child->name, 'ids' => $child->subtree()->pluck('id')->all()]);
        }

        $rows = $rows->map(fn ($r) => $r + $this->metrics($r['ids'], $from, $to));
        $total = $this->metrics($unit->subtree()->pluck('id')->all(), $from, $to);

        return ['rows' => $rows, 'total' => $total];
    }

    /** @param  list<int>  $unitIds */
    public function metrics(array $unitIds, Carbon $from, Carbon $to): array
    {
        $rows = LetterRecipient::query()
            ->whereIn('unit_id', $unitIds)
            ->where('role', '!=', RecipientRole::Cc->value)
            ->whereHas('letter', fn ($q) => $q->where('status', LetterStatus::Sent->value)
                ->whereBetween('sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->with('letter:id,due_date,sent_at')
            ->get(['id', 'letter_id', 'status', 'received_at', 'done_at']);

        $open = $rows->filter(fn ($r) => in_array($r->status->value, self::OPEN, true));
        $done = $rows->filter(fn ($r) => $r->done_at !== null);
        $hours = $done->map(fn ($r) => ($r->received_at ?? $r->letter->sent_at)->diffInMinutes($r->done_at) / 60)->filter(fn ($h) => $h >= 0);

        $outgoing = Letter::query()->whereIn('sender_unit_id', $unitIds)->where('status', LetterStatus::Sent->value)
            ->whereBetween('sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->count();

        return [
            'incoming' => $rows->count(),
            'outgoing' => $outgoing,
            'open' => $open->count(),
            'unreceived' => $rows->where('status', RecipientStatus::Pending)->count(),
            'overdue' => $open->filter(fn ($r) => $r->letter->due_date && $r->letter->due_date->lt(today()))->count(),
            'done' => $done->count(),
            'avg_hours' => $hours->isNotEmpty() ? round($hours->avg(), 1) : null,
            'completion' => $rows->count() ? (int) round($done->count() * 100 / $rows->count()) : null,
        ];
    }

    /** أرقام لرسم الوارد والصادر يومًا بيوم. */
    public function daily(array $unitIds, Carbon $from, Carbon $to): array
    {
        $in = LetterRecipient::query()->whereIn('unit_id', $unitIds)
            ->whereHas('letter', fn ($q) => $q->where('status', LetterStatus::Sent->value)->whereBetween('sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))
            ->with('letter:id,sent_at')->get()->groupBy(fn ($r) => $r->letter->sent_at->toDateString())->map->count();
        $out = Letter::query()->whereIn('sender_unit_id', $unitIds)->where('status', LetterStatus::Sent->value)
            ->whereBetween('sent_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['sent_at'])->groupBy(fn ($l) => $l->sent_at->toDateString())->map->count();

        $days = [];
        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $k = $d->toDateString();
            $days[] = ['date' => $k, 'in' => $in[$k] ?? 0, 'out' => $out[$k] ?? 0];
        }

        return $days;
    }
}
