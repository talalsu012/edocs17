<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تقرير تسليم وتسلّم — {{ $unit->name }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4; margin: 18mm; } @media print { .no-print { display: none } }</style>
</head>
<body class="bg-white font-sans text-slate-900">
<div class="mx-auto max-w-3xl p-8">
    <div class="no-print mb-6 text-left"><button onclick="window.print()" class="btn-primary">🖨 طباعة</button></div>

    <div class="mb-6 border-b-2 border-slate-800 pb-4 text-center">
        <div class="text-xl font-bold">{{ \App\Models\Setting::get('university_name_ar') }}</div>
        <div class="text-lg">تقرير تسليم وتسلّم صندوق المراسلات</div>
    </div>

    <table class="mb-6 w-full text-sm">
        <tr><td class="w-40 py-1 text-slate-500">الجهة</td><td class="font-semibold">{{ $unit->name }} ({{ $unit->code }})</td></tr>
        <tr><td class="py-1 text-slate-500">الشاغل الحالي</td><td>{{ $unit->activeAssignment?->user->name ?? '—' }}</td></tr>
        <tr><td class="py-1 text-slate-500">تاريخ التقرير</td><td>{{ now()->format('Y/m/d H:i') }}</td></tr>
    </table>

    <table class="mb-6 w-full border-collapse text-center text-sm">
        <tr class="bg-slate-100">
            <th class="border border-slate-300 p-2">وارد لم يُنجز</th>
            <th class="border border-slate-300 p-2">قيد الإجراء</th>
            <th class="border border-slate-300 p-2">متأخر عن موعد الرد</th>
            <th class="border border-slate-300 p-2">مسودات</th>
        </tr>
        <tr class="text-lg font-bold">
            <td class="border border-slate-300 p-3">{{ $report['pending'] }}</td>
            <td class="border border-slate-300 p-3">{{ $report['in_progress'] }}</td>
            <td class="border border-slate-300 p-3">{{ $report['overdue'] }}</td>
            <td class="border border-slate-300 p-3">{{ $report['drafts'] }}</td>
        </tr>
    </table>

    @if ($report['overdue_list']->isNotEmpty())
        <h2 class="mb-2 font-semibold">الخطابات المتأخرة</h2>
        <table class="mb-6 w-full border-collapse text-sm">
            <tr class="bg-slate-100">
                <th class="border border-slate-300 p-2">الرقم</th><th class="border border-slate-300 p-2">الموضوع</th>
                <th class="border border-slate-300 p-2">من</th><th class="border border-slate-300 p-2">موعد الرد</th>
            </tr>
            @foreach ($report['overdue_list'] as $r)
                <tr>
                    <td class="border border-slate-300 p-2" dir="ltr">{{ $r->letter->outgoing_no }}</td>
                    <td class="border border-slate-300 p-2">{{ $r->letter->subject }}</td>
                    <td class="border border-slate-300 p-2">{{ $r->letter->senderUnit?->name ?? $r->letter->originUnit?->name }}</td>
                    <td class="border border-slate-300 p-2">{{ $r->letter->due_date?->format('Y/m/d') }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="mt-16 grid grid-cols-2 gap-10 text-center text-sm">
        <div><div class="mb-12">المُسلِّم</div><div class="border-t border-slate-400 pt-1">الاسم والتوقيع</div></div>
        <div><div class="mb-12">المُستلِم</div><div class="border-t border-slate-400 pt-1">الاسم والتوقيع</div></div>
    </div>
</div>
</body>
</html>
