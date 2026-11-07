<?php

namespace App\Console\Commands;

use App\Models\Letter;
use App\Services\TrainingData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('edocs:training {--purge : حذف كل بيانات التدريب} {--days=60 : توزيع الخطابات على آخر عدد من الأيام} {--force : السماح في بيئة الإنتاج}')]
#[Description('إنشاء بيانات ومرفقات تدريبية واقعية (أو حذفها بـ --purge)')]
class TrainingDataCommand extends Command
{
    public function handle(TrainingData $training): int
    {
        if ($this->option('purge')) {
            $n = $training->purge();
            $this->info("تم حذف {$n} خطابًا تدريبيًا وإعادة ضبط عدادات الترقيم.");

            return self::SUCCESS;
        }

        if (app()->isProduction() && ! $this->option('force')) {
            $this->error('بيئة إنتاج: أضف --force إن كنت متأكدًا (يمكن الحذف لاحقًا بـ --purge).');

            return self::FAILURE;
        }

        if (Letter::where('is_training', true)->exists() && ! $this->confirm('توجد بيانات تدريب سابقة. إضافة مجموعة أخرى؟', false)) {
            return self::SUCCESS;
        }

        try {
            $s = $training->generate((int) $this->option('days'));
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("خطابات: {$s['letters']} — مرفقات: {$s['attachments']} — اطلاع على تعاميم: {$s['broadcast_reads']}");
        $this->line('للحذف قبل التشغيل الفعلي: php artisan edocs:training --purge');

        return self::SUCCESS;
    }
}
