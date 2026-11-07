<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>التحقق من خطاب — {{ \App\Models\Setting::get('university_name_ar') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-100 p-4 font-sans text-slate-800">
    <div class="card w-full max-w-md p-6">
        <div class="mb-4 text-center">
            <div class="text-lg font-bold">{{ \App\Models\Setting::get('university_name_ar') }}</div>
            <div class="text-sm text-slate-500">التحقق من صحة خطاب صادر</div>
        </div>

        @if (! $valid)
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-center text-red-800">
                <div class="text-3xl">✗</div>
                <div class="font-bold">رمز التحقق غير صحيح</div>
                <div class="text-sm">هذا الخطاب غير مسجل في النظام أو تم التلاعب بالرابط.</div>
            </div>
        @else
            @if ($letter->status === \App\Enums\LetterStatus::Cancelled)
                <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-center text-amber-900">
                    <div class="font-bold">⚠ خطاب صحيح لكنه ملغى</div>
                    <div class="text-sm">أُلغي بتاريخ {{ $letter->cancelled_at?->format('Y/m/d') }}</div>
                </div>
            @else
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-center text-emerald-800">
                    <div class="text-3xl">✓</div>
                    <div class="font-bold">خطاب صحيح ومسجل في النظام</div>
                </div>
            @endif

            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-slate-500">الرقم</dt><dd class="font-mono" dir="ltr">{{ $letter->outgoing_no }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">التاريخ</dt><dd>{{ $letter->sent_at?->format('Y/m/d') }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-slate-500">الجهة المصدرة</dt><dd>{{ $letter->senderUnit?->name ?? $letter->originUnit?->name }}</dd></div>
                @unless ($letter->is_confidential)
                    <div class="flex justify-between gap-4"><dt class="text-slate-500">الموضوع</dt><dd class="text-left">{{ $letter->subject }}</dd></div>
                    @if ($letter->recipients->isNotEmpty())
                        <div class="flex justify-between gap-4"><dt class="text-slate-500">إلى</dt><dd class="text-left">{{ $letter->recipients->map(fn ($r) => $r->unit?->name)->filter()->join('، ') }}</dd></div>
                    @endif
                @else
                    <div class="text-center text-xs text-slate-500">خطاب سري — تفاصيل الموضوع والمستلمين غير معروضة.</div>
                @endunless
            </dl>
        @endif
    </div>
</body>
</html>
