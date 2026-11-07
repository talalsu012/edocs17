<?php

namespace App\Models;

use App\Enums\LetterStatus;
use App\Enums\LetterType;
use App\Enums\Priority;
use App\Enums\RecipientRole;
use App\Models\Scopes\LetterVisibility;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable([
    'type', 'subject', 'body', 'priority', 'is_confidential', 'due_date',
    'sender_unit_id', 'origin_unit_id', 'sender_user_id', 'approver_user_id', 'approved_at', 'is_acting',
    'parent_letter_id', 'template_id', 'template_values', 'status',
])]
class Letter extends Model
{
    protected function casts(): array
    {
        return [
            'type' => LetterType::class,
            'priority' => Priority::class,
            'status' => LetterStatus::class,
            'is_confidential' => 'boolean',
            'is_acting' => 'boolean',
            'is_training' => 'boolean',
            'due_date' => 'date',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'template_values' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // الخطاب المرسَل لا يُحذف أبدًا — يُلغى مع ذكر السبب
        static::deleting(function (Letter $letter) {
            if ($letter->sent_at !== null) {
                throw new \LogicException('لا يجوز حذف خطاب مرسَل؛ استخدم الإلغاء المسبب.');
            }
        });
    }

    /* ---------- الرؤية الهرمية ---------- */

    /** الخطابات التي يحق للمستخدم رؤيتها. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        LetterVisibility::apply($query, $user);
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /* ---------- العلاقات ---------- */

    public function senderUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sender_unit_id');
    }

    public function originUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'origin_unit_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Letter::class, 'parent_letter_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Letter::class, 'parent_letter_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(LetterRecipient::class);
    }

    public function toRecipients(): HasMany
    {
        return $this->recipients()->where('role', RecipientRole::To->value);
    }

    public function ccRecipients(): HasMany
    {
        return $this->recipients()->where('role', RecipientRole::Cc->value);
    }

    public function broadcastTargets(): HasMany
    {
        return $this->hasMany(BroadcastTarget::class);
    }

    public function broadcastReads(): HasMany
    {
        return $this->hasMany(BroadcastRead::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(LetterEvent::class)->orderBy('created_at');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /* ---------- مساعدات ---------- */

    public function isBroadcast(): bool
    {
        return $this->type === LetterType::Broadcast;
    }

    public function isPersonal(): bool
    {
        return $this->sender_unit_id === null;
    }

    public function isSent(): bool
    {
        return in_array($this->status, [LetterStatus::Sent, LetterStatus::Cancelled], true);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && $this->isSent();
    }

    /** السلسلة الكاملة من الخطاب الأصلي حتى هذا الخطاب. */
    public function thread(): Collection
    {
        $chain = collect([$this]);
        $current = $this;
        while ($current->parent_letter_id && ($current = $current->parent)) {
            $chain->prepend($current);
        }

        return $chain;
    }

    /** رمز التحقق من صحة الخطاب المطبوع (موقّع بمفتاح التطبيق، لا يمكن تخمينه). */
    public function verificationCode(): string
    {
        return substr(hash_hmac('sha256', $this->id.'|'.$this->outgoing_no.'|'.$this->sent_at?->timestamp, config('app.key')), 0, 20);
    }

    public function verificationUrl(): string
    {
        return route('letters.verify', ['letter' => $this->id, 'code' => $this->verificationCode()]);
    }

    /** تسجيل حدث في سجل الحركة. */
    public function logEvent(string $action, ?User $actor = null, array $extra = []): LetterEvent
    {
        return $this->events()->create(array_merge([
            'action' => $action,
            'actor_user_id' => $actor?->id,
            'actor_unit_id' => $actor?->primaryUnit()?->id,
            'ip_address' => request()?->ip(),
        ], $extra));
    }
}
