<?php

namespace App\Services;

use App\Enums\NumberDirection;
use App\Enums\RecipientStatus;
use App\Models\BroadcastRead;
use App\Models\Letter;
use App\Models\LetterRecipient;
use App\Models\Numbering;
use App\Models\StaffAllowedTarget;
use App\Models\Template;
use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * بيانات تدريب واقعية: خطابات بكل الأنواع والحالات، بمرفقات، موزعة على آخر 60 يومًا.
 * تُنشأ عبر LetterService نفسها (ترقيم وسجل حركة حقيقيان) وتُوسَم is_training لتُحذف دفعة واحدة.
 */
class TrainingData
{
    /** @var Collection<int, array{unit: Unit, user: User}> */
    protected Collection $holders;

    protected array $stats = ['letters' => 0, 'attachments' => 0, 'broadcast_reads' => 0];

    /** @var list<int> */
    protected array $created = [];

    public function __construct(protected LetterService $letters, protected BroadcastService $broadcasts) {}

    /** @return array{letters:int, attachments:int, broadcast_reads:int} */
    public function generate(int $days = 60): array
    {
        $this->holders = UnitAssignment::query()->active()->with('unit', 'user')->get()
            ->filter(fn ($a) => $a->unit->is_active && $a->user->is_active)
            ->unique('unit_id')
            ->map(fn ($a) => ['unit' => $a->unit, 'user' => $a->user])
            ->values();

        if ($this->holders->count() < 3) {
            throw new \RuntimeException('بيانات التدريب تحتاج 3 جهات على الأقل لها مستخدمون مكلّفون. كلّف مستخدمين أولًا من شاشة التكليفات.');
        }

        mt_srand(2026); // نتائج ثابتة في كل تشغيل
        $scenarios = $this->scenarios();
        $start = now()->copy()->subDays($days)->setTime(8, 0);
        $step = max(1, intdiv($days * 24 * 60, count($scenarios) + 1));

        try {
            foreach (array_values($scenarios) as $i => $scenario) {
                $at = $start->copy()->addMinutes($step * ($i + 1));
                $at->setTime(min(15, max(8, $at->hour)), $at->minute); // ساعات الدوام
                $scenario($at);
            }
        } finally {
            Carbon::setTestNow();
        }

        Letter::whereIn('id', $this->created)->update(['is_training' => true]);
        $this->stats['letters'] = count($this->created);

        return $this->stats;
    }

    /** حذف كل بيانات التدريب وإعادة ضبط عدادات الترقيم. */
    public function purge(): int
    {
        $ids = Letter::where('is_training', true)->orderByDesc('id')->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        DB::transaction(function () use ($ids) {
            foreach (DB::table('attachments')->whereIn('letter_id', $ids)->pluck('file_path') as $path) {
                Storage::disk('local')->delete($path);
            }
            DB::table('attachments')->whereIn('letter_id', $ids)->delete();
            DB::table('broadcast_reads')->whereIn('letter_id', $ids)->delete();
            DB::table('broadcast_targets')->whereIn('letter_id', $ids)->delete();
            DB::table('letter_events')->whereIn('letter_id', $ids)->delete();
            DB::table('letter_recipients')->whereIn('letter_id', $ids)->update(['referred_from_id' => null]);
            DB::table('letter_recipients')->whereIn('letter_id', $ids)->delete();
            DB::table('letters')->whereIn('parent_letter_id', $ids)->whereNotIn('id', $ids)->update(['parent_letter_id' => null]);
            foreach ($ids->chunk(200) as $chunk) { // الردود قبل الأصل
                DB::table('letters')->whereIn('id', $chunk)->update(['parent_letter_id' => null]);
                DB::table('letters')->whereIn('id', $chunk)->delete();
            }

            // إعادة العدادات إلى آخر رقم مستخدم فعليًا
            foreach (Numbering::all() as $n) {
                $max = $n->direction === NumberDirection::Outgoing
                    ? Letter::where('outgoing_year', $n->year)->where(fn ($q) => $q->where('sender_unit_id', $n->unit_id)
                        ->orWhere(fn ($w) => $w->whereNull('sender_unit_id')->where('origin_unit_id', $n->unit_id)))->max('outgoing_seq')
                    : LetterRecipient::where('unit_id', $n->unit_id)->where('incoming_year', $n->year)->max('incoming_seq');
                $n->update(['last_no' => (int) $max]);
            }
        });

        return $ids->count();
    }

    /* ---------------- السيناريوهات ---------------- */

    /** @return list<callable(Carbon): void> */
    protected function scenarios(): array
    {
        $subjects = [
            ['طلب حصر أعضاء هيئة التدريس للعام الجامعي', 'نرجو التكرم بموافاتنا بحصر أعضاء هيئة التدريس بالجهة موضحًا فيه الدرجة العلمية والتخصص، وذلك في موعد أقصاه نهاية الأسبوع.'],
            ['ترشيح ممثل لعضوية لجنة الامتحانات', 'بالإشارة إلى الموضوع أعلاه، نرجو التكرم بترشيح ممثل عن جهتكم لعضوية لجنة الامتحانات المركزية.'],
            ['طلب صيانة معمل الحاسوب', 'نفيدكم بتعطل عدد من أجهزة معمل الحاسوب، ونرجو التكرم بالتوجيه لإجراء الصيانة اللازمة.'],
            ['اعتماد جدول المحاضرات للفصل الدراسي', 'نرفق لكم جدول المحاضرات المقترح للفصل الدراسي القادم للاطلاع والاعتماد.'],
            ['تقرير الأداء الربع سنوي', 'نرفق لسيادتكم تقرير الأداء للربع الحالي متضمنًا المؤشرات الرئيسية والتحديات.'],
            ['طلب تصديق ميزانية نشاط طلابي', 'نرجو التكرم بالموافقة على تصديق الميزانية المرفقة للنشاط الطلابي المزمع إقامته.'],
            ['إفادة بشأن تأخر صرف الحوافز', 'نرجو إفادتنا بأسباب تأخر صرف حوافز العاملين بالجهة عن الشهر الماضي.'],
            ['دعوة لاجتماع مجلس الكلية', 'نتشرف بدعوتكم لحضور اجتماع المجلس يوم الأحد القادم الساعة العاشرة صباحًا بقاعة الاجتماعات.'],
            ['طلب توفير مستلزمات مكتبية', 'نرجو التكرم بالتوجيه بتوفير المستلزمات المكتبية الموضحة بالكشف المرفق.'],
            ['مقترح برنامج تدريبي للعاملين', 'نرفق لكم مقترح البرنامج التدريبي للعاملين للدراسة وإبداء الرأي.'],
            ['حصر الأصول والعهد', 'نرجو موافاتنا بكشف الأصول والعهد بالجهة وفق النموذج المعتمد.'],
            ['الموافقة على مشاركة في مؤتمر علمي', 'نرجو التكرم بالموافقة على مشاركة أحد أعضاء هيئة التدريس في المؤتمر العلمي المرفقة تفاصيله.'],
        ];

        $s = [];
        $pairs = $this->pairs(16);

        // خطابات مباشرة بحالات مختلفة
        foreach (array_slice($pairs, 0, 10) as $k => [$from, $to]) {
            [$subject, $body] = $subjects[$k % count($subjects)];
            $outcome = ['done', 'done', 'archived', 'in_progress', 'received', 'pending', 'done', 'overdue', 'in_progress', 'archived'][$k];
            $s[] = fn (Carbon $at) => $this->direct($at, $from, [$to], [], $subject, $body, $outcome,
                priority: ['normal', 'urgent', 'normal', 'very_urgent'][$k % 4], attach: $k % 3 === 0);
        }

        // خطاب متعدد مع نسخ
        [$from, $to] = $pairs[10];
        $cc = $this->holders->first(fn ($h) => ! in_array($h['unit']->id, [$from['unit']->id, $to['unit']->id], true));
        $s[] = fn (Carbon $at) => $this->direct($at, $from, [$to], array_filter([$cc]), $subjects[3][0], $subjects[3][1], 'done', attach: true);

        // سري
        [$from, $to] = $pairs[11];
        $s[] = fn (Carbon $at) => $this->direct($at, $from, [$to], [], 'ملاحظات سرية بشأن تقييم الأداء', 'نرفق لكم ملاحظات سرية حول تقييم أداء الجهة للاطلاع واتخاذ ما يلزم.', 'received', confidential: true);

        // ملغى
        [$from, $to] = $pairs[12];
        $s[] = fn (Carbon $at) => $this->direct($at, $from, [$to], [], 'تعديل موعد الاجتماع (أُرسل بالخطأ)', 'نفيدكم بتعديل موعد الاجتماع.', 'cancel');

        // إحالة: جهة أعلى ترسل لجهة لها جهة تابعة، فتحيله إليها
        $parentHolder = $this->holders->first(fn ($h) => $this->holders->contains(fn ($c) => $c['unit']->parent_id === $h['unit']->id));
        if ($parentHolder) {
            $child = $this->holders->first(fn ($c) => $c['unit']->parent_id === $parentHolder['unit']->id);
            $sender = $this->holders->first(fn ($h) => ! in_array($h['unit']->id, [$parentHolder['unit']->id, $child['unit']->id], true));
            $s[] = fn (Carbon $at) => $this->referral($at, $sender, $parentHolder, $child, $subjects[0][0].' (للإحالة)', $subjects[0][1]);
            $s[] = fn (Carbon $at) => $this->referral($at, $sender, $parentHolder, $child, $subjects[10][0], $subjects[10][1], childDone: false);
        }

        // ردود
        foreach (array_slice($pairs, 13, 2) as $k => [$from, $to]) {
            $s[] = fn (Carbon $at) => $this->withReply($at, $from, $to, $subjects[6 + $k][0], $subjects[6 + $k][1]);
        }

        // طلبات الموظفين من النماذج
        foreach ($this->staffRequests() as $req) {
            $s[] = $req;
        }

        // تعاميم
        $broadcaster = $this->holders->first(fn ($h) => $h['unit']->can_broadcast_all);
        if ($broadcaster) {
            $s[] = fn (Carbon $at) => $this->broadcast($at, $broadcaster, ['scope' => 'all_staff'], 'تعميم بشأن مواعيد الدوام الرسمي', "يُعمم على جميع العاملين الالتزام بمواعيد الدوام الرسمي من الساعة الثامنة صباحًا وحتى الثالثة ظهرًا.\nويسري هذا التعميم اعتبارًا من تاريخه.", 0.6);
            $s[] = fn (Carbon $at) => $this->broadcast($at, $broadcaster, ['scope' => 'unit_type', 'unit_type' => 'college'], 'تعميم بشأن مواعيد رفع النتائج', 'على جميع الكليات رفع نتائج الامتحانات في موعد أقصاه أسبوعان من تاريخ آخر امتحان.', 0.4);
        }
        $sub = $this->holders->first(fn ($h) => ! $h['unit']->can_broadcast_all && $this->holders->contains(fn ($c) => $c['unit']->parent_id === $h['unit']->id));
        if ($sub) {
            $s[] = fn (Carbon $at) => $this->broadcast($at, $sub, ['scope' => 'subtree', 'root_unit_id' => $sub['unit']->id], 'تعميم داخلي بشأن تسليم التقارير الشهرية', 'على جميع الأقسام التابعة تسليم التقارير الشهرية في الأسبوع الأول من كل شهر.', 0.7);
        }

        return $s;
    }

    /** @return list<array{0: array, 1: array}> أزواج (مرسل، مستلم) مختلفة */
    protected function pairs(int $n): array
    {
        $h = $this->holders->values();
        $c = $h->count();
        $out = [];
        for ($i = 0; count($out) < $n; $i++) {
            $a = $h[$i % $c];
            $b = $h[($i * 3 + 1) % $c];
            if ($a['unit']->id === $b['unit']->id) {
                $b = $h[($i + 1) % $c];
            }
            $out[] = [$a, $b];
        }

        return $out;
    }

    protected function at(Carbon $at, int $addHours = 0): void
    {
        Carbon::setTestNow($at->copy()->addHours($addHours));
    }

    protected function direct(Carbon $at, array $from, array $to, array $cc, string $subject, string $body, string $outcome,
        string $priority = 'normal', bool $attach = false, bool $confidential = false): Letter
    {
        $this->at($at);
        $letter = $this->letters->saveDraft($from['user'], [
            'mode' => 'unit', 'unit_id' => $from['unit']->id, 'subject' => $subject, 'body' => $body,
            'priority' => $priority, 'is_confidential' => $confidential,
            'due_date' => $outcome === 'overdue' ? $at->copy()->addDays(5)->toDateString()
                : ($outcome !== 'cancel' && mt_rand(0, 1) ? $at->copy()->addDays(14)->toDateString() : null),
            'to' => array_map(fn ($t) => $t['unit']->id, $to),
            'cc' => array_map(fn ($t) => $t['unit']->id, $cc),
        ]);
        if ($attach) {
            $this->attach($letter, $from['user']);
        }
        $this->letters->send($from['user'], $letter);
        $this->created[] = $letter->id;

        if ($outcome === 'cancel') {
            $this->at($at, 2);
            $this->letters->cancel($from['user'], $letter->fresh(), 'أُرسل بالخطأ قبل اعتماد الموعد النهائي');

            return $letter;
        }

        foreach ($letter->fresh('recipients')->recipients as $i => $row) {
            $holder = $this->holderOf($row->unit_id);
            if (! $holder || $outcome === 'pending' || $outcome === 'overdue') {
                continue;
            }
            $this->at($at, 3 + $i);
            $this->letters->open($holder, $letter->fresh('recipients'));
            if ($row->role->value === 'cc') {
                continue;
            }
            $steps = match ($outcome) {
                'in_progress' => ['in_progress'],
                'done' => ['in_progress', 'done'],
                'archived' => ['in_progress', 'done', 'archived'],
                default => [],
            };
            foreach ($steps as $j => $st) {
                $this->at($at, 24 * ($j + 1));
                $this->letters->setRecipientStatus($holder, $row->fresh(), RecipientStatus::from($st),
                    $st === 'done' ? 'تم الإنجاز وإفادة الجهة المرسلة' : null);
            }
        }

        // توجيه من جهة أعلى أحيانًا
        $ancestor = $this->holders->first(fn ($h) => $h['unit']->isAncestorOf($from['unit']));
        if ($ancestor && mt_rand(0, 2) === 0) {
            $this->at($at, 30);
            $this->letters->addDirective($ancestor['user'], $letter->fresh(), 'نأمل المتابعة والإفادة بما تم.');
        }

        return $letter;
    }

    protected function referral(Carbon $at, array $sender, array $parent, array $child, string $subject, string $body, bool $childDone = true): void
    {
        $letter = $this->direct($at, $sender, [$parent], [], $subject, $body, 'received', attach: true);
        $row = $letter->recipients()->where('unit_id', $parent['unit']->id)->first();
        $this->at($at, 26);
        $new = $this->letters->refer($parent['user'], $row, $child['unit']->id, 'للإفادة');
        $this->at($at, 30);
        $this->letters->open($child['user'], $letter->fresh('recipients'));
        if ($childDone) {
            $this->at($at, 50);
            $this->letters->setRecipientStatus($child['user'], $new->fresh(), RecipientStatus::Done, 'تمت الإفادة');
        }
    }

    protected function withReply(Carbon $at, array $from, array $to, string $subject, string $body): void
    {
        $letter = $this->direct($at, $from, [$to], [], $subject, $body, 'in_progress');
        $this->at($at, 48);
        $reply = $this->letters->saveDraft($to['user'], [
            'mode' => 'unit', 'unit_id' => $to['unit']->id, 'subject' => 'رد: '.$subject,
            'body' => "بالإشارة إلى خطابكم رقم {$letter->fresh()->outgoing_no}، نفيدكم بأنه قد تم اتخاذ اللازم.",
            'to' => [$from['unit']->id], 'parent_letter_id' => $letter->id,
        ]);
        $this->letters->send($to['user'], $reply);
        $this->created[] = $reply->id;

        $row = $letter->recipients()->where('unit_id', $to['unit']->id)->first();
        $this->letters->setRecipientStatus($to['user'], $row, RecipientStatus::Done, 'تم الرد');
        $this->at($at, 52);
        $this->letters->open($from['user'], $reply->fresh('recipients'));
    }

    /** @return list<callable(Carbon): void> */
    protected function staffRequests(): array
    {
        $allowed = StaffAllowedTarget::pluck('unit_id')->all();
        $staff = User::where('is_active', true)->whereNotNull('home_unit_id')
            ->whereDoesntHave('assignments', fn ($q) => $q->active())->limit(4)->get();
        $templates = Template::latestActive()->forStaff()->get()->keyBy('code');
        $out = [];

        $requests = [
            ['leave_request', ['نوع_الإجازة' => 'سنوية', 'العدد' => '14', 'من_تاريخ' => '2026/12/01', 'إلى_تاريخ' => '2026/12/14', 'البديل' => 'الزميل المناوب', 'توصية_المدير' => '']],
            ['experience_certificate', ['نوع_الشهادة' => 'خبرة', 'الغرض' => 'التقديم للدراسات العليا', 'الجهة_الموجه_إليها' => 'من يهمه الأمر']],
            ['advance_request', ['المبلغ' => '150000', 'عدد_الأقساط' => '6']],
            ['leave_request', ['نوع_الإجازة' => 'مرضية', 'العدد' => '3', 'من_تاريخ' => '2026/11/10', 'إلى_تاريخ' => '2026/11/12', 'البديل' => '—', 'توصية_المدير' => '']],
        ];

        foreach ($staff as $i => $user) {
            [$code, $values] = $requests[$i % count($requests)];
            $t = $templates[$code] ?? null;
            if (! $t || ! in_array($t->default_recipient_unit_id, $allowed, true)) {
                continue;
            }
            $out[] = function (Carbon $at) use ($user, $t, $values, $i) {
                $this->at($at);
                $values += ['اسم_الموظف' => $user->name, 'الرقم_الوظيفي' => $user->employee_no, 'التاريخ' => $at->format('Y/m/d'),
                    'جهة_المرسل' => $user->homeUnit?->name, 'رقم_الصادر' => ''];
                $letter = $this->letters->saveDraft($user, [
                    'mode' => 'personal', 'subject' => $t->renderSubject($values), 'body' => $t->render($values),
                    'to' => [$t->default_recipient_unit_id], 'template_id' => $t->id,
                ]);
                if ($i % 2 === 0) {
                    $this->attach($letter, $user, image: true);
                }
                $this->letters->send($user, $letter);
                $this->created[] = $letter->id;

                if ($holder = $this->holderOf($t->default_recipient_unit_id)) {
                    $this->at($at, 5);
                    $this->letters->open($holder, $letter->fresh('recipients'));
                    if ($i % 3 !== 2) {
                        $this->at($at, 30);
                        $this->letters->setRecipientStatus($holder, $letter->recipients()->first(), RecipientStatus::Done, 'تمت الموافقة');
                    }
                }
            };
        }

        return $out;
    }

    protected function broadcast(Carbon $at, array $from, array $target, string $subject, string $body, float $readRatio): void
    {
        $this->at($at);
        $letter = $this->letters->saveDraft($from['user'], [
            'mode' => 'broadcast', 'unit_id' => $from['unit']->id, 'subject' => $subject, 'body' => $body, 'broadcast' => $target,
        ]);
        $this->attach($letter, $from['user']);
        $this->letters->send($from['user'], $letter);
        $this->created[] = $letter->id;

        $audience = $this->broadcasts->audience($letter->fresh('broadcastTargets.rootUnit'))->pluck('id');
        $readers = $audience->shuffle()->take((int) round($audience->count() * $readRatio));
        foreach ($readers as $k => $uid) {
            BroadcastRead::firstOrCreate(['letter_id' => $letter->id, 'user_id' => $uid], ['read_at' => $at->copy()->addHours(2 + $k)]);
            $this->stats['broadcast_reads']++;
        }
    }

    protected function holderOf(?int $unitId): ?User
    {
        return $unitId ? $this->holders->first(fn ($h) => $h['unit']->id === $unitId)['user'] ?? null : null;
    }

    /** مرفقات تجريبية: PDF صالح وصورة PNG. */
    protected function attach(Letter $letter, User $by, bool $image = false): void
    {
        $name = $image ? 'صورة-المستند.png' : 'مرفق-'.$letter->id.'.pdf';
        $content = $image ? $this->samplePng() : $this->samplePdf("Kassala University - Training attachment #{$letter->id}");
        $path = "letters/{$letter->id}/training-".($image ? 'scan.png' : 'doc.pdf');
        Storage::disk('local')->put($path, $content);

        $letter->attachments()->create([
            'file_name' => $name, 'file_path' => $path,
            'mime_type' => $image ? 'image/png' : 'application/pdf',
            'size' => strlen($content), 'uploaded_by' => $by->id,
        ]);
        $this->stats['attachments']++;
    }

    /** ملف PDF صغير صالح بصفحة A4 وسطر نصي. */
    protected function samplePdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 18 Tf 72 760 Td ({$text}) Tj 0 -30 Td /F1 12 Tf (Sample document for training purposes only.) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref
0 '.(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }

    /** صورة PNG صغيرة (رمادية 120×80) دون الحاجة لامتداد GD. */
    protected function samplePng(): string
    {
        $w = 120;
        $h = 80;
        $raw = '';
        for ($y = 0; $y < $h; $y++) {
            $raw .= "\0".str_repeat($y < 12 ? "\x1f\x6f\x5c" : ($y % 10 === 0 ? "\xcc\xcc\xcc" : "\xf5\xf5\xf5"), $w);
        }
        $chunk = fn ($type, $data) => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $w, $h, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress($raw))
            .$chunk('IEND', '');
    }
}
