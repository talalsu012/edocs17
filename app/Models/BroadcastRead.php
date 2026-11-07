<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** من ضغط «علمت» على التعميم. */
#[Fillable(['letter_id', 'user_id', 'unit_id', 'read_at'])]
class BroadcastRead extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
