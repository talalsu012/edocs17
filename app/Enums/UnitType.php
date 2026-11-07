<?php

namespace App\Enums;

enum UnitType: string
{
    case University = 'university';
    case Office = 'office';
    case Administration = 'administration';
    case Deanship = 'deanship';
    case College = 'college';
    case Department = 'department';
    case Center = 'center';
    case Section = 'section';

    public function label(): string
    {
        return match ($this) {
            self::University => 'جامعة',
            self::Office => 'مكتب',
            self::Administration => 'إدارة',
            self::Deanship => 'عمادة',
            self::College => 'كلية',
            self::Department => 'قسم',
            self::Center => 'مركز',
            self::Section => 'وحدة',
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
