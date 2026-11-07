<div class="relative" wire:poll.60s x-data @click.outside="$wire.set('open', false)">
    <button type="button" wire:click="$toggle('open')" class="relative flex w-full items-center justify-between rounded-lg bg-white/10 px-3 py-2 text-sm hover:bg-white/20">
        <span>🔔 التنبيهات</span>
        @if ($this->unreadCount)
            <span class="badge bg-amber-400 text-amber-950">{{ $this->unreadCount }}</span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 z-30 mt-2 w-80 overflow-hidden rounded-xl border border-slate-200 bg-white text-slate-800 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                <span class="text-sm font-semibold">التنبيهات</span>
                @if ($this->unreadCount)
                    <button wire:click="markAllRead" class="text-xs text-brand-700 hover:underline">تعليم الكل كمقروء</button>
                @endif
            </div>
            <div class="max-h-96 overflow-y-auto">
                @forelse ($this->latest as $n)
                    <button wire:click="go('{{ $n->id }}')" wire:key="n-{{ $n->id }}"
                        @class(['block w-full border-b border-slate-50 px-4 py-2.5 text-right hover:bg-slate-50', 'bg-brand-50/70' => ! $n->read_at])>
                        <div class="flex items-start gap-2">
                            <span>{{ $n->data['icon'] ?? '🔔' }}</span>
                            <div class="min-w-0 flex-1">
                                <div @class(['truncate text-sm', 'font-semibold' => ! $n->read_at])>{{ $n->data['title'] ?? '' }}</div>
                                <div class="truncate text-xs text-slate-600">{{ $n->data['subject'] ?? '' }}</div>
                                @if (! empty($n->data['note'])) <div class="truncate text-xs text-amber-700">{{ $n->data['note'] }}</div> @endif
                                <div class="text-[11px] text-slate-400">{{ $n->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-slate-500">لا توجد تنبيهات.</div>
                @endforelse
            </div>
        </div>
    @endif
</div>
