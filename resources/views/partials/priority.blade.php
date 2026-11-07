@php($p = $priority instanceof \App\Enums\Priority ? $priority : \App\Enums\Priority::from($priority))
@if ($p !== \App\Enums\Priority::Normal)
    <span @class(['badge', 'bg-amber-100 text-amber-800' => $p === \App\Enums\Priority::Urgent, 'bg-red-100 text-red-700' => $p === \App\Enums\Priority::VeryUrgent])>{{ $p->label() }}</span>
@endif
