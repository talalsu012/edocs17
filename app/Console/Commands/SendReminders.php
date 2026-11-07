<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('edocs:reminders')]
#[Description('التذكير اليومي: اقتراب موعد الرد والتأخر (يُجدول يوميًا)')]
class SendReminders extends Command
{
    public function handle(Notifier $notifier): int
    {
        $s = $notifier->dailyReminders();
        $this->info("تذكير باقتراب الموعد: {$s['due_soon']} — تنبيه تأخر: {$s['overdue']}");

        return self::SUCCESS;
    }
}
