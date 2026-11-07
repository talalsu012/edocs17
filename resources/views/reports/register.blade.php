@php($S = fn ($k) => \App\Models\Setting::get($k))
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>دفتر {{ $dir === 'in' ? 'الوارد' : 'الصادر' }} — {{ $unit->name }}</title>
    @vite(['resources/css/app.css'])
    <style>@page { size: A4 landscape; margin: 12mm; } @media print { .no-print { display: none } } td, th { vertical-align: top; }</style>
</head>
<body class="bg-white font-sans text-slate-900">
<div class="mx-auto max-w-6xl p-6">
    <div class="no-print mb-4 flex justify-between">
        <span class="text-sm text-slate-500">{{ $entries->count() }} قيدًا</span>
        <button onclick="window.print()" class="btn-primary">🖨 طباعة / حفظ PDF</button>
    </div>

    <div class="mb-4 flex items-end justify-between border-b-2 border-slate-800 pb-3">
        <div>
            <div class="text-lg font-bold">{{ $S('university_name_ar') }}</div>
            <div>{{ $unit->name }}</div>
        </div>
        <div class="text-center text-xl font-bold">دفتر {{ $dir === 'in' ? 'الوارد' : 'الصادر' }}</div>
        <div class="text-left text-sm">من {{ $from->format('Y/m/d') }}<br>إلى {{ $to->format('Y/m/d') }}</div>
    </div>

    <table class="w-full border-collapse text-xs">
        <thead class="bg-slate-100">
            @if ($dir === 'in')
                <tr>
                    <th class="border border-slate-300 p-1.5">م</th>
                    <th class="border border-slate-300 p-1.5">رقم الوارد</th>
                    <th class="border border-slate-300 p-1.5">تاريخ الاستلام</th>
                    <th class="border border-slate-300 p-1.5">رقم الصادر</th>
                    <th class="border border-slate-300 p-1.5">تاريخه</th>
                    <th class="border border-slate-300 p-1.5">الجهة المرسلة</th>
                    <th class="border border-slate-300 p-1.5">الموضوع</th>
                    <th class="border border-slate-300 p-1.5">الصفة</th>
                    <th class="border border-slate-300 p-1.5">الحالة</th>
                </tr>
            @else
                <tr>
                    <th class="border border-slate-300 p-1.5">م</th>
                    <th class="border border-slate-300 p-1.5">رقم الصادر</th>
                    <th class="border border-slate-300 p-1.5">التاريخ</th>
                    <th class="border border-slate-300 p-1.5">إلى</th>
                    <th class="border border-slate-300 p-1.5">الموضوع</th>
                    <th class="border border-slate-300 p-1.5">الأهمية</th>
                    <th class="border border-slate-300 p-1.5">بواسطة</th>
                    <th class="border border-slate-300 p-1.5">ملاحظات</th>
                </tr>
            @endif
        </thead>
        <tbody>
            @forelse ($entries as $i => $e)
                @if ($dir === 'in')
                    <tr>
                        <td class="border border-slate-300 p-1.5 text-center">{{ $i + 1 }}</td>
                        <td class="border border-slate-300 p-1.5 font-mono" dir="ltr">{{ $e->incoming_no ?? '—' }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->received_at?->format('Y/m/d') ?? 'لم يُستلم' }}</td>
                        <td class="border border-slate-300 p-1.5 font-mono" dir="ltr">{{ $e->letter->outgoing_no }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->letter->sent_at->format('Y/m/d') }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->letter->senderUnit?->name ?? $e->letter->sender?->name.' — '.$e->letter->originUnit?->name }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->letter->subject }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->role->label() }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->letter->status === \App\Enums\LetterStatus::Cancelled ? 'ملغى' : $e->status->label() }}</td>
                    </tr>
                @else
                    <tr>
                        <td class="border border-slate-300 p-1.5 text-center">{{ $i + 1 }}</td>
                        <td class="border border-slate-300 p-1.5 font-mono" dir="ltr">{{ $e->outgoing_no }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->sent_at->format('Y/m/d') }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->isBroadcast() ? 'تعميم' : $e->recipients->map(fn ($r) => ($r->role === \App\Enums\RecipientRole::Cc ? 'نسخة: ' : '').$r->unit?->name)->filter()->join('، ') }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->subject }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->priority->label() }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->sender?->name }}</td>
                        <td class="border border-slate-300 p-1.5">{{ $e->status === \App\Enums\LetterStatus::Cancelled ? 'ملغى: '.$e->cancel_reason : '' }}</td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="9" class="border border-slate-300 p-6 text-center text-slate-500">لا توجد قيود في هذه الفترة.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="mt-4 text-left text-xs text-slate-500">طُبع في {{ now()->format('Y/m/d H:i') }} — {{ auth()->user()->name }}</div>
</div>
</body>
</html>
