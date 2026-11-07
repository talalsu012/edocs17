@php
    $colors = [
        'pending' => 'bg-slate-100 text-slate-700',
        'received' => 'bg-sky-100 text-sky-800',
        'in_progress' => 'bg-amber-100 text-amber-800',
        'referred' => 'bg-violet-100 text-violet-800',
        'done' => 'bg-emerald-100 text-emerald-800',
        'archived' => 'bg-slate-200 text-slate-600',
    ];
@endphp
<span class="badge {{ $colors[$status->value] ?? '' }}">{{ $status->label() }}</span>
