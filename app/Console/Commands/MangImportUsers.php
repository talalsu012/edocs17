<?php

namespace App\Console\Commands;

use App\Models\Mang\ReadOnlyMangModel;
use App\Services\MangDirectory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mang:import-users {--password= : كلمة المرور المبدئية للحسابات الجديدة} {--dry-run : عرض ما سيحدث دون تنفيذ}')]
#[Description('إنشاء حسابات الدخول للموظفين النشطين في mang وتحديث بيانات الحسابات القائمة')]
class MangImportUsers extends Command
{
    public function handle(MangDirectory $mang): int
    {
        if (! ReadOnlyMangModel::available()) {
            $this->error('تعذّر الاتصال بقاعدة mang. راجع إعدادات MANG_DB_* في ملف .env');

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('كلمة المرور المبدئية للحسابات الجديدة');
        if (! $password || strlen($password) < 8) {
            $this->error('كلمة المرور يجب ألا تقل عن 8 أحرف.');

            return self::FAILURE;
        }

        $s = $mang->importUsers($password, (bool) $this->option('dry-run'));
        $this->info("حسابات جديدة: {$s['created']} — محدّثة: {$s['updated']}");

        return self::SUCCESS;
    }
}
