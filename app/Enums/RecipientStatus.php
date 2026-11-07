<?php

namespace App\Enums;

enum RecipientStatus: string
{
    case Pending = 'pending';
    case Received = 'received';
    case InProgress = 'in_progress';
    case Referred = 'referred';
    case Done = 'done';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'لم يُستلم',
            self::Received => 'مستلم',
            self::InProgress => 'قيد الإجراء',
            self::Referred => 'محال',
            self::Done => 'مُنجز',
            self::Archived => 'محفوظ',
        };
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(fn (self $c) => $c->label(), self::cases()),
        );
    }
}
