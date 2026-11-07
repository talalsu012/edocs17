@php
    $max = max(1, collect($daily)->max(fn ($d) => max($d['in'], $d['out'])));
    $fmtH = fn ($h) => $h === null ? '—' : ($h < 48 ? $h.' ساعة' : round($h / 24, 1).' يوم');
@endphp
<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">لوحة المؤشرات</h1>
            <div class="flex items-center gap-2 text-sm text-slate-500">
                @if ($canUp) <button wire:click="up" class="text-brand-700 hover:underline">↑ {{ $unit->parent->name }}</button> › @endif
                <span class="font-medium text-slate-700">{{ $unit->name }}</span>
                <span>· من {{ $from->format('Y/m/d') }}</span>
            </div>
        </div>
        <div class="flex rounded-lg border border-slate-300 bg-white p-0.5 text-sm">
            @foreach ([7 => '7 أيام', 30 => '30 يومًا', 90 => '90 يومًا', 365 => 'سنة'] as $d => $label)
                <button wire:click="$set('days', {{ $d }})" @class(['rounded-md px-3 py-1', 'bg-brand-600 text-white' => $days === $d, 'text-slate-600 hover:bg-slate-50' => $days !== $d])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- المؤشرات الرئيسية --}}
    <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ([
            ['الوارد', $total['incoming'], null],
            ['الصادر', $total['outgoing'], null],
            ['المعلّق', $total['open'], $total['unreceived'] ? $total['unreceived'].' لم يُستلم بعد' : null],
            ['المتأخر', $total['overdue'], null],
            ['متوسط زمن الإنجاز', $fmtH($total['avg_hours']), 'من الاستلام حتى الإنجاز'],
            ['نسبة الإنجاز', $total['completion'] === null ? '—' : $total['completion'].'٪', $total['done'].' مُنجز'],
        ] as [$label, $value, $hint])
            <div class="card p-4">
                <div class="text-xs text-slate-500">{{ $label }}</div>
                <div @class(['mt-1 text-2xl font-bold', 'text-red-700' => $label === 'المتأخر' && $value, 'text-slate-800' => ! ($label === 'المتأخر' && $value)])>{{ $value }}</div>
                @if ($hint) <div class="text-[11px] text-slate-400">{{ $hint }}</div> @endif
            </div>
        @endforeach
    </div>

    {{-- الوارد والصادر يوميًا --}}
    <div class="card mb-5 p-5" x-data="{ tip: null }">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">الوارد والصادر يوميًا</h2>
            <div class="flex items-center gap-4 text-xs text-slate-600">
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#0f8a6a"></span> الوارد</span>
                <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background:#d97706"></span> الصادر</span>
            </div>
        </div>
        <div class="relative">
            <div class="flex h-40 items-end gap-[2px] border-b border-slate-200" dir="ltr">
                @foreach ($daily as $d)
                    <div class="group relative flex h-full flex-1 items-end justify-center gap-[2px] hover:bg-slate-50"
                         @mouseenter="tip = @js(['date' => $d['date'], 'in' => $d['in'], 'out' => $d['out']])" @mouseleave="tip = null">
                        <div class="w-full max-w-3 rounded-t-[4px]" style="background:#0f8a6a; height: {{ $d['in'] / $max * 100 }}%"></div>
                        <div class="w-full max-w-3 rounded-t-[4px]" style="background:#d97706; height: {{ $d['out'] / $max * 100 }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-1 flex justify-between text-[11px] text-slate-400" dir="ltr">
                <span>{{ \Illuminate\Support\Carbon::parse($daily[0]['date'])->format('m/d') }}</span>
                <span>الأعلى: {{ $max }}</span>
                <span>{{ \Illuminate\Support\Carbon::parse(end($daily)['date'])->format('m/d') }}</span>
            </div>
            <div x-show="tip" x-cloak class="pointer-events-none absolute left-1/2 top-0 -translate-x-1/2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-700 shadow-md">
                <div class="font-semibold" x-text="tip?.date"></div>
                <div>الوارد: <span x-text="tip?.in"></span> · الصادر: <span x-text="tip?.out"></span></div>
            </div>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-3">
        {{-- حسب الجهات التابعة --}}
        <div class="card overflow-x-auto lg:col-span-2">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-right">الجهة</th>
                        <th class="px-2 py-2">الوارد</th><th class="px-2 py-2">الصادر</th><th class="px-2 py-2">المعلّق</th>
                        <th class="px-2 py-2">المتأخر</th><th class="px-2 py-2">متوسط الإنجاز</th><th class="px-2 py-2">الإنجاز</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $r)
                        <tr class="border-t border-slate-100 text-center">
                            <td class="px-3 py-2 text-right">
                                @if ($i > 0 && $r['unit']->children()->exists())
                                    <button wire:click="drill({{ $r['unit']->id }})" class="text-brand-700 hover:underline">{{ $r['label'] }} ‹</button>
                                @else
                                    {{ $r['label'] }}
                                @endif
                            </td>
                            <td class="px-2 py-2">{{ $r['incoming'] }}</td>
                            <td class="px-2 py-2">{{ $r['outgoing'] }}</td>
                            <td class="px-2 py-2">{{ $r['open'] }}</td>
                            <td @class(['px-2 py-2', 'font-semibold text-red-700' => $r['overdue']])>{{ $r['overdue'] }}</td>
                            <td class="px-2 py-2 text-xs">{{ $fmtH($r['avg_hours']) }}</td>
                            <td class="px-2 py-2 text-xs">{{ $r['completion'] === null ? '—' : $r['completion'].'٪' }}</td>
                        </tr>
                    @endforeach
                    <tr class="border-t-2 border-slate-300 bg-slate-50 text-center font-semibold">
                        <td class="px-3 py-2 text-right">الإجمالي</td>
                        <td class="px-2 py-2">{{ $total['incoming'] }}</td><td class="px-2 py-2">{{ $total['outgoing'] }}</td>
                        <td class="px-2 py-2">{{ $total['open'] }}</td><td class="px-2 py-2">{{ $total['overdue'] }}</td>
                        <td class="px-2 py-2 text-xs">{{ $fmtH($total['avg_hours']) }}</td>
                        <td class="px-2 py-2 text-xs">{{ $total['completion'] === null ? '—' : $total['completion'].'٪' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- دفتر الوارد والصادر --}}
        @if ($isHolder)
            <form method="GET" action="{{ route('reports.register') }}" target="_blank" class="card space-y-3 p-5">
                <h2 class="font-semibold">📒 دفتر الوارد والصادر</h2>
                <input type="hidden" name="unit" value="{{ $unit->id }}">
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">من</label><input type="date" name="from" value="{{ $from->toDateString() }}" class="input"></div>
                    <div><label class="label">إلى</label><input type="date" name="to" value="{{ today()->toDateString() }}" class="input"></div>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <button name="dir" value="in" class="btn-secondary">دفتر الوارد</button>
                    <button name="dir" value="out" class="btn-secondary">دفتر الصادر</button>
                </div>
                <p class="text-xs text-slate-500">يُفتح للطباعة أو الحفظ PDF. متاح لمستخدم الجهة فقط.</p>
            </form>
        @else
            <div class="card p-5 text-sm text-slate-500">المؤشرات أعداد فقط؛ لا تعرض محتوى المراسلات. دفتر الوارد والصادر متاح لمستخدم الجهة نفسها.</div>
        @endif
    </div>
</div>
