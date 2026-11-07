<?php

namespace App\Models;

use App\Enums\NumberDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * تسلسل أرقام الصادر والوارد لكل جهة ولكل سنة، آمن مع الطلبات المتزامنة.
 */
#[Fillable(['unit_id', 'year', 'direction', 'last_no'])]
class Numbering extends Model
{
    protected $table = 'numbering';

    protected function casts(): array
    {
        return ['direction' => NumberDirection::class];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * يحجز الرقم التالي ويعيد [seq, year, formatted]
     *
     * @return array{seq:int, year:int, number:string}
     */
    public static function next(Unit $unit, NumberDirection $direction, ?int $year = null): array
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($unit, $direction, $year) {
            static::query()->insertOrIgnore([
                'unit_id' => $unit->id, 'year' => $year, 'direction' => $direction->value,
                'last_no' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $row = static::query()
                ->where(['unit_id' => $unit->id, 'year' => $year, 'direction' => $direction->value])
                ->lockForUpdate()
                ->first();

            $row->increment('last_no');

            return [
                'seq' => $row->last_no,
                'year' => $year,
                'number' => static::format($unit, $year, $row->last_no, $direction),
            ];
        });
    }

    /** مثال: جك/م ب ش/2026/0153 — والوارد: جك/م ب ش/و/2026/0042 */
    public static function format(Unit $unit, int $year, int $seq, NumberDirection $direction = NumberDirection::Outgoing): string
    {
        $parts = [Setting::get('number_prefix', 'جك'), $unit->code];
        if ($direction === NumberDirection::Incoming) {
            $parts[] = 'و';
        }
        $parts[] = $year;
        $parts[] = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        return implode('/', $parts);
    }
}
