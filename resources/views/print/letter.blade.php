@php
    use App\Support\Dates;
    use App\Enums\RecipientRole;
    $to = $letter->recipients->where('role', RecipientRole::To);
    $cc = $letter->recipients->where('role', RecipientRole::Cc);
    $name = fn ($r) => $r->unit?->name ?? $r->user?->name;
    $S = fn ($k) => \App\Models\Setting::get($k);
    $logoPath = $S('logo_path');
    $logo = $logoPath && file_exists(public_path($logoPath)) ? asset($logoPath)
        : (file_exists(public_path('images/logo.png')) ? asset('images/logo.png') : null);
    $footer = array_filter([$S('letterhead_address'), $S('letterhead_phone') ? 'هاتف: '.$S('letterhead_phone') : null, $S('letterhead_email'), $S('letterhead_website')]);
    $signer = $letter->sender_unit_id ? ($letter->approver ?? $letter->sender) : $letter->sender;
    $signerTitle = $letter->sender_unit_id
        ? trim(($letter->senderUnit->head_title ?? '').' — '.$letter->senderUnit->name, ' —')
        : ($signer?->displayJobTitle() ? $signer->displayJobTitle().' — '.$letter->originUnit?->name : $letter->originUnit?->name);
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{{ $letter->outgoing_no }} — {{ $letter->subject }}</title>
    @vite(['resources/css/app.css', 'resources/js/print.js'])
    <style>
        @page { size: A4; margin: 14mm 16mm; }
        body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { width: 210mm; min-height: 297mm; padding: 14mm 16mm; margin: 0 auto; background: #fff; position: relative; display: flex; flex-direction: column; }
        @media print { .no-print { display: none !important; } body { background: #fff !important; } .sheet { width: auto; min-height: auto; padding: 0; margin: 0; box-shadow: none; } }
        .watermark { position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 120px; font-weight: 800; color: rgba(220, 38, 38, .12); transform: rotate(-25deg); pointer-events: none; }
    </style>
</head>
<body class="bg-slate-200 font-sans text-slate-900">
    <div class="no-print sticky top-0 z-10 flex items-center justify-between bg-slate-800 px-6 py-3 text-sm text-white">
        <span>معاينة الطباعة — اختر «حفظ كـ PDF» من نافذة الطباعة لحفظ الملف.</span>
        <button onclick="window.print()" class="rounded-md bg-white px-4 py-1.5 font-medium text-slate-800">🖨 طباعة</button>
    </div>

    <div class="sheet my-6 shadow-lg print:my-0">
        @if ($letter->status === \App\Enums\LetterStatus::Cancelled)
            <div class="watermark">ملغى</div>
        @endif

        {{-- الترويسة --}}
        <header class="flex items-start justify-between border-b-2 border-brand-700 pb-3">
            <div class="w-1/3 text-sm leading-6">
                <div class="text-lg font-bold">{{ $S('university_name_ar') }}</div>
                <div>{{ $letter->senderUnit?->name ?? $letter->originUnit?->name }}</div>
            </div>
            <div class="w-1/3 text-center">
                @if ($logo)
                    <img src="{{ $logo }}" alt="شعار {{ $S('university_name_ar') }}" class="mx-auto h-20">
                @else
                    <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full border-2 border-brand-700 text-xs text-brand-700">شعار الجامعة</div>
                @endif
            </div>
            <div class="w-1/3 text-left text-sm leading-6">
                <div class="text-base font-bold" dir="ltr">{{ $S('university_name_en') }}</div>
                <div>الرقم: <span class="font-mono" dir="ltr">{{ $letter->outgoing_no }}</span></div>
                <div>التاريخ: {{ Dates::gregorian($letter->sent_at) }}م</div>
                @if ($S('show_hijri') && ($h = Dates::hijri($letter->sent_at))) <div>الموافق: {{ $h }}</div> @endif
            </div>
        </header>

        {{-- الخطاب --}}
        <main class="flex-1 pt-6 text-[15px] leading-8">
            <div class="mb-1 flex gap-2">
                @if ($letter->is_confidential) <span class="rounded border border-red-600 px-2 text-sm font-bold text-red-700">سري</span> @endif
                @if ($letter->priority !== \App\Enums\Priority::Normal) <span class="rounded border border-amber-600 px-2 text-sm font-bold text-amber-700">{{ $letter->priority->label() }}</span> @endif
            </div>

            @if ($letter->isBroadcast())
                <div class="mb-4 text-center text-lg font-bold underline underline-offset-8">تعميم</div>
            @elseif ($to->count() === 1)
                <div class="font-semibold">السيد/ {{ $to->first()->unit?->head_title ? $to->first()->unit->head_title.' — ' : '' }}{{ $name($to->first()) }}</div>
                <div class="mb-3 pr-24 font-semibold">المحترم</div>
            @elseif ($to->count() > 1)
                <div class="font-semibold">السادة/</div>
                <ul class="mb-3 list-inside list-disc pr-4">
                    @foreach ($to as $r) <li>{{ $name($r) }}</li> @endforeach
                </ul>
                <div class="mb-3 pr-24 font-semibold">المحترمون</div>
            @endif

            @unless ($letter->isBroadcast() || str_contains((string) $letter->body, 'السلام عليكم'))
                <div class="mb-3">السلام عليكم ورحمة الله وبركاته،</div>
            @endunless

            <div class="mb-4 text-center font-bold">الموضوع: {{ $letter->subject }}</div>

            @if ($letter->parent)
                <div class="mb-2 text-sm text-slate-600">إلحاقًا / إشارةً إلى الخطاب رقم <span dir="ltr">{{ $letter->parent->outgoing_no }}</span> بتاريخ {{ Dates::gregorian($letter->parent->sent_at) }}م</div>
            @endif

            <div class="whitespace-pre-line text-justify">{{ $letter->body }}</div>

            {{-- التوقيع --}}
            <div class="mt-10 mr-auto w-64 text-center">
                <div class="font-bold">{{ $signer?->displayName() }}</div>
                <div class="text-sm">{{ $signerTitle }}</div>
                @if ($letter->is_acting) <div class="text-sm font-semibold">(بالإنابة)</div> @endif
                <div class="mt-10 grid grid-cols-2 gap-4 text-xs text-slate-500">
                    <div class="border-t border-dashed border-slate-400 pt-1">التوقيع</div>
                    <div class="border-t border-dashed border-slate-400 pt-1">الختم</div>
                </div>
            </div>

            @if ($cc->isNotEmpty())
                <div class="mt-8 text-sm">
                    <div class="font-semibold">صورة إلى:</div>
                    <ul class="list-inside list-disc pr-4">
                        @foreach ($cc as $r) <li>{{ $name($r) }} — للعلم</li> @endforeach
                    </ul>
                </div>
            @endif

            @if ($letter->attachments->isNotEmpty())
                <div class="mt-3 text-sm">المرفقات: {{ $letter->attachments->count() }} ({{ $letter->attachments->pluck('file_name')->join('، ') }})</div>
            @endif
        </main>

        {{-- التذييل: رمز التحقق --}}
        <footer class="mt-8 flex items-end justify-between border-t border-slate-300 pt-3 text-xs text-slate-500">
            <div class="leading-5">
                @if ($footer) <div class="font-medium text-slate-600">{{ implode(' · ', $footer) }}</div> @endif
                <div>صدر إلكترونيًا من {{ $S('system_name') }} — {{ $S('university_name_ar') }}</div>
                <div>للتحقق من صحة هذا الخطاب امسح الرمز، أو افتح الرابط:</div>
                <div class="font-mono" dir="ltr">{{ $letter->verificationUrl() }}</div>
            </div>
            <div class="h-24 w-24 shrink-0" data-qr="{{ $letter->verificationUrl() }}" @if ($autoprint) data-autoprint @endif></div>
        </footer>
    </div>
</body>
</html>
