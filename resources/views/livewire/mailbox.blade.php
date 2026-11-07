<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">{{ $title }}</h1>
            @if ($unit)
                <p class="text-sm text-slate-500">{{ $unit->name }}</p>
            @endif
        </div>
        <div class="w-full sm:w-72">
            <input type="search" wire:model.live.debounce.400ms="search" class="input" placeholder="بحث بالموضوع أو رقم الصادر/الوارد…">
        </div>
    </div>

    <div class="card overflow-hidden">
        @forelse ($letters as $letter)
            @php
                $myRow = $letter->recipients->first(fn ($r) => ($unit && $r->unit_id === $unit->id) || $r->user_id === auth()->id());
                $isNew = ($box === 'inbox' && $myRow?->status === \App\Enums\RecipientStatus::Pending)
                    || ($box === 'broadcasts' && ! in_array($letter->id, $readBroadcasts, true));
                $party = in_array($box, ['outbox', 'drafts'])
                    ? 'إلى: '.$letter->recipients->map(fn ($r) => $r->unit?->name ?? $r->user?->name)->filter()->join('، ')
                    : 'من: '.($letter->senderUnit?->name ?? $letter->sender?->name.' — '.$letter->originUnit?->name);
            @endphp
            <a href="{{ route('letters.show', $letter) }}" wire:navigate wire:key="l-{{ $letter->id }}"
               @class(['flex flex-col gap-1 border-b border-slate-100 px-4 py-3 last:border-0 hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-4', 'bg-brand-50/60' => $isNew])>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($isNew) <span class="h-2 w-2 rounded-full bg-brand-600"></span> @endif
                        <span @class(['truncate', 'font-bold' => $isNew, 'font-medium' => ! $isNew])>{{ $letter->subject }}</span>
                        @include('partials.priority', ['priority' => $letter->priority])
                        @if ($letter->is_confidential) <span class="badge bg-rose-100 text-rose-700">سري</span> @endif
                        @if ($letter->is_training) <span class="badge bg-sky-100 text-sky-700">تدريب</span> @endif
                        @if ($letter->status === \App\Enums\LetterStatus::Cancelled) <span class="badge bg-red-100 text-red-700">ملغى</span> @endif
                        @if ($letter->isOverdue() && $myRow && ! in_array($myRow->status, [\App\Enums\RecipientStatus::Done, \App\Enums\RecipientStatus::Archived]))
                            <span class="badge bg-red-600 text-white">متأخر</span>
                        @endif
                    </div>
                    <div class="truncate text-xs text-slate-500">{{ $party }}</div>
                </div>
                <div class="flex shrink-0 items-center gap-3 text-xs text-slate-500">
                    @if ($myRow && $box !== 'drafts') @include('partials.recipient-status', ['status' => $myRow->status]) @endif
                    <span class="font-mono" dir="ltr">{{ $letter->outgoing_no ?? 'مسودة' }}</span>
                    <span>{{ ($letter->sent_at ?? $letter->updated_at)->format('Y/m/d') }}</span>
                </div>
            </a>
        @empty
            <div class="px-4 py-16 text-center text-slate-500">
                <div class="mb-2 text-3xl">📭</div>
                لا توجد خطابات في {{ $title }}{{ $search ? ' تطابق البحث' : '' }}.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $letters->links() }}</div>
</div>
