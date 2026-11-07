<?php

namespace App\Enums;

enum TemplateAudience: string
{
    case Unit = 'unit';
    case Staff = 'staff';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'مستخدم جهة',
            self::Staff => 'موظف',
            self::Both => 'الجميع',
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
