<?php

namespace App\Models\Mang;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * أساس موديلات قاعدة الموظفين mang: قراءة فقط.
 * أي محاولة حفظ أو حذف ترمي استثناء — مصدر الحقيقة هو نظام الموارد البشرية.
 */
abstract class ReadOnlyMangModel extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    public function getConnectionName()
    {
        return config('edocs.mang.connection', 'mang');
    }

    public function save(array $options = [])
    {
        throw new LogicException('قاعدة الموظفين mang للقراءة فقط.');
    }

    public function delete()
    {
        throw new LogicException('قاعدة الموظفين mang للقراءة فقط.');
    }

    /** هل قاعدة mang مفعّلة ويمكن الوصول إليها؟ */
    public static function available(): bool
    {
        if (! config('edocs.mang.enabled')) {
            return false;
        }

        static $ok = null;

        if ($ok === null) {
            try {
                (new static)->getConnection()->getPdo();
                $ok = true;
            } catch (\Throwable) {
                $ok = false;
            }
        }

        return $ok;
    }
}
