<?php

namespace App\Notifications;

use App\Models\Letter;
use Illuminate\Notifications\Notification;

/**
 * تنبيه داخل النظام مرتبط بخطاب. القناة: database (ولاحقًا SMS/بريد بإضافة قنوات هنا).
 */
class LetterNotification extends Notification
{
    public const NEW = 'new';

    public const REFERRED = 'referred';

    public const REPLY = 'reply';

    public const DIRECTIVE = 'directive';

    public const DUE_SOON = 'due_soon';

    public const OVERDUE = 'overdue';

    public const OVERDUE_SENDER = 'overdue_sender';

    public function __construct(
        public Letter $letter,
        public string $kind,
        public ?string $note = null,
        public ?int $recipientId = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $l = $this->letter;
        $from = $l->senderUnit?->name ?? $l->sender?->name;

        [$icon, $title] = match ($this->kind) {
            self::NEW => ['✉️', 'خطاب جديد من '.$from],
            self::REFERRED => ['↪', 'خطاب محال إليكم'.($this->note ? ' — '.$this->note : '')],
            self::REPLY => ['↩', 'رد على خطابكم من '.$from],
            self::DIRECTIVE => ['📌', 'توجيه جديد على خطاب'],
            self::DUE_SOON => ['⏰', 'يقترب موعد الرد ('.$l->due_date?->format('Y/m/d').')'],
            self::OVERDUE => ['⚠️', 'تأخر الرد عن الموعد ('.$l->due_date?->format('Y/m/d').')'],
            self::OVERDUE_SENDER => ['⚠️', 'جهة لم ترد على خطابكم في الموعد'],
            default => ['🔔', 'تنبيه'],
        };

        return [
            'kind' => $this->kind,
            'icon' => $icon,
            'title' => $title,
            // الموضوع لا يُخزَّن في الخطاب السري؛ يظهر عند فتح الخطاب فقط
            'subject' => $l->is_confidential ? 'خطاب سري' : $l->subject,
            'number' => $l->outgoing_no,
            'letter_id' => $l->id,
            'recipient_id' => $this->recipientId,
            'priority' => $l->priority->value,
            'note' => $this->kind === self::DIRECTIVE ? $this->note : null,
        ];
    }
}
