<?php

namespace App\Enums;

enum Priority: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
    case VeryUrgent = 'very_urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'عادي',
            self::Urgent => 'عاجل',
            self::VeryUrgent => 'عاجل جدًا',
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
