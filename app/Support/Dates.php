<?php

namespace App\Support;

use Carbon\CarbonInterface;
use IntlDateFormatter;

/**
 * التاريخ الميلادي مع إمكانية إظهار الهجري في الطباعة.
 */
class Dates
{
    /** مثال: 7 نوفمبر 2026 */
    public static function gregorian(?CarbonInterface $date): string
    {
        return $date ? self::format($date, 'ar', 'd MMMM y') ?? $date->format('Y/m/d') : '';
    }

    /** مثال: 26 جمادى الأولى 1448هـ (أم القرى) */
    public static function hijri(?CarbonInterface $date): ?string
    {
        $text = $date ? self::format($date, 'ar_SA@calendar=islamic-umalqura', 'd MMMM y') : null;

        return $text ? $text.'هـ' : null;
    }

    protected static function format(CarbonInterface $date, string $locale, string $pattern): ?string
    {
        if (! class_exists(IntlDateFormatter::class)) {
            return null;
        }

        $f = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE,
            $date->getTimezone()->getName(), IntlDateFormatter::TRADITIONAL, $pattern);
        // أرقام لاتينية لسهولة القراءة والمطابقة مع رقم الخطاب
        $out = $f->format($date->getTimestamp());

        return $out === false ? null : strtr($out, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);
    }
}
