<?php

namespace App\Models;

use App\Enums\BroadcastScope;
use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['letter_id', 'scope', 'root_unit_id', 'unit_type'])]
class BroadcastTarget extends Model
{
    protected function casts(): array
    {
        return [
            'scope' => BroadcastScope::class,
            'unit_type' => UnitType::class,
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function rootUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'root_unit_id');
    }
}
