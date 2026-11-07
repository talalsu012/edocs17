<?php

namespace App\Enums;

enum BroadcastScope: string
{
    case AllUnits = 'all_units';
    case Subtree = 'subtree';
    case UnitType = 'unit_type';
    case AllStaff = 'all_staff';

    public function label(): string
    {
        return match ($this) {
            self::AllUnits => 'كل الجهات',
            self::Subtree => 'فرع من الشجرة',
            self::UnitType => 'نوع جهات',
            self::AllStaff => 'كل الموظفين',
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
