<?php

namespace App\Enums;

enum NumberDirection: string
{
    case Outgoing = 'out';
    case Incoming = 'in';

    public function label(): string
    {
        return match ($this) {
            self::Outgoing => 'صادر',
            self::Incoming => 'وارد',
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
