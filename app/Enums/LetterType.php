<?php

namespace App\Enums;

enum LetterType: string
{
    case Direct = 'direct';
    case Multi = 'multi';
    case Broadcast = 'broadcast';
    case Reply = 'reply';
    case Referral = 'referral';
    case StaffRequest = 'staff_request';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'خطاب مباشر',
            self::Multi => 'خطاب متعدد',
            self::Broadcast => 'تعميم',
            self::Reply => 'رد',
            self::Referral => 'إحالة',
            self::StaffRequest => 'طلب موظف',
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
