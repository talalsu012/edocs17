<?php

namespace App\Console\Commands;

use App\Models\Mang\ReadOnlyMangModel;
use App\Services\MangDirectory;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('mang:import-units {--dry-run : عرض ما سيحدث دون تنفيذ}')]
#[Description('استيراد/تحديث الكليات والأقسام من قاعدة الموظفين mang إلى شجرة الجهات')]
class MangImportUnits extends Command
{
    public function handle(MangDirectory $mang): int
    {
        if (! ReadOnlyMangModel::available()) {
            $this->error('تعذّر الاتصال بقاعدة mang. راجع إعدادات MANG_DB_* في ملف .env');

            return self::FAILURE;
        }

        $s = $mang->importUnits((bool) $this->option('dry-run'));
        $this->info("جهات جديدة: {$s['created']} — محدّثة: {$s['updated']} — دون تغيير: {$s['skipped']}");

        return self::SUCCESS;
    }
}
