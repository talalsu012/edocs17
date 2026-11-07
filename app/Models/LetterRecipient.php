<?php

namespace App\Models;

use App\Enums\RecipientRole;
use App\Enums\RecipientStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * مستلم خطاب (جهة أو شخص) بحالة ورقم وارد مستقلين.
 */
#[Fillable([
    'letter_id', 'unit_id', 'user_id', 'role', 'referred_from_id', 'referral_note',
    'incoming_no', 'incoming_year', 'incoming_seq', 'status',
    'received_at', 'received_by', 'done_at', 'done_by', 'archived_at',
])]
class LetterRecipient extends Model
{
    protected function casts(): array
    {
        return [
            'role' => RecipientRole::class,
            'status' => RecipientStatus::class,
            'received_at' => 'datetime',
            'done_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function referredFrom(): BelongsTo
    {
        return $this->belongsTo(LetterRecipient::class, 'referred_from_id');
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(LetterRecipient::class, 'referred_from_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }

    /** هل يتطلب منه إجراء/رد؟ (النسخة للعلم فقط) */
    public function requiresAction(): bool
    {
        return $this->role !== RecipientRole::Cc;
    }
}
