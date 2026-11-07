<?php

namespace App\Enums;

enum AssignmentKind: string
{
    case Original = 'original';
    case Acting = 'acting';

    public function label(): string
    {
        return match ($this) {
            self::Original => 'أصيل',
            self::Acting => 'بالإنابة',
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
