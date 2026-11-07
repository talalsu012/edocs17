<?php

namespace App\Enums;

enum RecipientRole: string
{
    case To = 'to';
    case Cc = 'cc';
    case Referred = 'referred';

    public function label(): string
    {
        return match ($this) {
            self::To => 'إلى',
            self::Cc => 'نسخة لـ',
            self::Referred => 'محال إليه',
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
