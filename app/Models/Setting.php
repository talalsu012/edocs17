<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * إعدادات الجامعة. القيم الافتراضية في config/edocs.php (settings)،
 * وما يُحفظ من الواجهة يتقدم عليها. تُخزَّن مؤقتًا وتُمسح عند الحفظ.
 */
#[Fillable(['key', 'value', 'updated_by'])]
class Setting extends Model
{
    protected const CACHE_KEY = 'edocs.settings';

    public static function get(string $key, mixed $default = null): mixed
    {
        $all = static::all_();
        $value = array_key_exists($key, $all) ? $all[$key] : config("edocs.settings.$key", $default);

        return static::cast($key, $value);
    }

    /** @param  array<string, mixed>  $values */
    public static function put(array $values, ?User $by = null): void
    {
        foreach ($values as $key => $value) {
            static::updateOrCreate(['key' => $key], [
                'value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (is_bool($value) ? (int) $value : $value),
                'updated_by' => $by?->id,
            ]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, string|null> */
    protected static function all_(): array
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, fn () => Schema::hasTable('settings')
                ? static::query()->pluck('value', 'key')->all()
                : []);
        } catch (\Throwable) {
            return [];
        }
    }

    protected static function cast(string $key, mixed $value): mixed
    {
        $type = gettype(config("edocs.settings.$key"));

        return match (true) {
            $value === null => null,
            $type === 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            $type === 'integer' => (int) $value,
            $type === 'array' => is_array($value) ? $value : (json_decode((string) $value, true) ?? []),
            default => $value,
        };
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
