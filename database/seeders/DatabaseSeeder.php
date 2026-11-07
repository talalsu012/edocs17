<?php

namespace Database\Seeders;

use App\Models\Mang\ReadOnlyMangModel;
use App\Services\MangDirectory;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * إن كانت قاعدة الموظفين mang متاحة: الهيكل الإداري + الكليات والأقسام من mang.
     * وإلا: الهيكل الإداري + كليات تجريبية (للاختبار).
     *
     * لا نستخدم WithoutModelEvents لأن مسار الجهة (path) يُحسب في أحداث الموديل.
     */
    public function run(): void
    {
        $fromMang = ! app()->runningUnitTests() && ReadOnlyMangModel::available();

        $units = new UnitSeeder;
        $units->withSampleColleges = ! $fromMang;
        $units->setContainer($this->container)->setCommand($this->command)->__invoke();

        if ($fromMang) {
            $stats = app(MangDirectory::class)->importUnits();
            $this->command?->info("الكليات والأقسام من mang: {$stats['created']} جهة");
        }

        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            TemplateSeeder::class,
        ]);

        if ($fromMang) {
            $this->command?->warn('لإنشاء حسابات الموظفين من mang: php artisan mang:import-users');
        }
    }
}
