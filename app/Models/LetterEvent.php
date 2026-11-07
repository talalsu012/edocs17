<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل الحركة والتدقيق — إضافة فقط، لا تعديل ولا حذف.
 */
#[Fillable(['letter_id', 'recipient_id', 'action', 'from_status', 'to_status', 'actor_user_id', 'actor_unit_id', 'note', 'meta', 'ip_address'])]
class LetterEvent extends Model
{
    public const UPDATED_AT = null;

    // أسماء الأحداث
    public const CREATED = 'created';

    public const SUBMITTED = 'submitted';

    public const APPROVED = 'approved';

    public const SENT = 'sent';

    public const VIEWED = 'viewed';

    public const RECEIVED = 'received';

    public const STATUS_CHANGED = 'status_changed';

    public const REFERRED = 'referred';

    public const REPLIED = 'replied';

    public const DIRECTIVE = 'directive';      // توجيه/تعليق من جهة أعلى

    public const ACKNOWLEDGED = 'acknowledged'; // «علمت» على تعميم

    public const CANCELLED = 'cancelled';

    public const PRINTED = 'printed';

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('سجل الحركة لا يُعدَّل.'));
        static::deleting(fn () => throw new LogicException('سجل الحركة لا يُحذف.'));
    }

    public function letter(): BelongsTo
    {
        return $this->belongsTo(Letter::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(LetterRecipient::class, 'recipient_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function actorUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'actor_unit_id');
    }
}
