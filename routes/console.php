<?php

use Illuminate\Support\Facades\Schedule;

// التذكير اليومي باقتراب موعد الرد والتأخر.
// يتطلب تشغيل المجدول: php artisan schedule:work (تطوير) أو مهمة cron/Task Scheduler تستدعي schedule:run كل دقيقة.
Schedule::command('edocs:reminders')->dailyAt('07:30')->withoutOverlapping();
