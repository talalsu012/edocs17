@php
    use App\Enums\RecipientStatus;
    use App\Models\LetterEvent;
    $actionLabels = [
        LetterEvent::CREATED => 'أنشأ المسودة', LetterEvent::SENT => 'أرسل الخطاب', LetterEvent::VIEWED => 'فتح الخطاب',
        LetterEvent::RECEIVED => 'استلم الخطاب', LetterEvent::STATUS_CHANGED => 'غيّر الحالة', LetterEvent::DIRECTIVE => 'أضاف توجيهًا',
        LetterEvent::CANCELLED => 'ألغى الخطاب', LetterEvent::REFERRED => 'أحال الخطاب', LetterEvent::REPLIED => 'ردّ',
        LetterEvent::ACKNOWLEDGED => 'علم بالتعميم', LetterEvent::PRINTED => 'طبع الخطاب', LetterEvent::APPROVED => 'اعتمد', LetterEvent::SUBMITTED => 'رفع للاعتماد',
    ];
    $statusLabel = fn ($v) => $v ? (RecipientStatus::tryFrom($v)?->label() ?? \App\Enums\LetterStatus::tryFrom($v)?->label() ?? $v) : null;
@endphp
<div class="grid gap-5 lg:grid-cols-3">
    {{-- الخطاب --}}
    <div class="space-y-5 lg:col-span-2">
        <div class="card p-6">
            @if ($letter->status === \App\Enums\LetterStatus::Cancelled)
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <strong>خطاب ملغى</strong> — {{ $letter->cancel_reason }}
                    <span class="text-xs">({{ $letter->canceller?->name }}، {{ $letter->cancelled_at?->format('Y/m/d H:i') }})</span>
                </div>
            @endif

            <div class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <div class="mb-1 flex flex-wrap items-center gap-2">
                        <span class="badge bg-slate-100 text-slate-700">{{ $letter->type->label() }}</span>
                        @include('partials.priority', ['priority' => $letter->priority])
                        @if ($letter->is_confidential) <span class="badge bg-rose-100 text-rose-700">سري</span> @endif
                        @if ($letter->is_acting) <span class="badge bg-slate-100 text-slate-600">بالإنابة</span> @endif
                        @if ($letter->is_training) <span class="badge bg-sky-100 text-sky-700">بيانات تدريب</span> @endif
                    </div>
                    <h1 class="text-xl font-bold text-slate-800">{{ $letter->subject }}</h1>
                </div>
                <div class="text-left text-sm text-slate-600">
                    <div>الرقم: <span class="font-mono" dir="ltr">{{ $letter->outgoing_no }}</span></div>
                    <div>التاريخ: {{ $letter->sent_at?->format('Y/m/d') }}</div>
                    @if ($letter->due_date)
                        <div @class(['font-semibold text-red-600' => $letter->isOverdue()])>موعد الرد: {{ $letter->due_date->format('Y/m/d') }}</div>
                    @endif
                </div>
            </div>

            <dl class="mb-5 grid gap-2 text-sm sm:grid-cols-2">
                <div><dt class="inline text-slate-500">من:</dt>
                    <dd class="inline font-medium">{{ $letter->senderUnit?->name ?? $letter->sender?->name.' — '.$letter->originUnit?->name }}</dd></div>
                <div><dt class="inline text-slate-500">بواسطة:</dt>
                    <dd class="inline">{{ $letter->sender?->name }}</dd></div>
                @if ($letter->parent)
                    <div class="sm:col-span-2"><dt class="inline text-slate-500">إلحاقًا / ردًا على:</dt>
                        <dd class="inline"><a href="{{ route('letters.show', $letter->parent) }}" wire:navigate class="text-brand-700 underline">{{ $letter->parent->outgoing_no }} — {{ $letter->parent->subject }}</a></dd></div>
                @endif
            </dl>

            <div class="whitespace-pre-line leading-8 text-slate-800">{{ $letter->body }}</div>

            @if ($letter->attachments->isNotEmpty())
                @php
                    $files = $letter->attachments->values()->map(fn ($a) => [
                        'name' => $a->file_name, 'size' => number_format($a->size / 1024).'KB',
                        'type' => $a->isImage() ? 'image' : ($a->isPreviewable() ? 'pdf' : 'other'),
                        'view' => $a->isPreviewable() ? route('attachments.view', $a) : null,
                        'download' => route('attachments.download', $a),
                    ]);
                @endphp
                <div class="mt-6 border-t border-slate-100 pt-4"
                     x-data="{ files: @js($files), i: null, get f() { return this.i === null ? null : this.files[this.i] } }"
                     @keydown.escape.window="i = null"
                     @keydown.arrow-left.window="if (i !== null && i < files.length - 1) i++"
                     @keydown.arrow-right.window="if (i !== null && i > 0) i--">
                    <h3 class="mb-2 text-sm font-semibold text-slate-600">المرفقات ({{ $letter->attachments->count() }})</h3>
                    <ul class="flex flex-wrap gap-2">
                        @foreach ($letter->attachments as $k => $a)
                            <li>
                                <button type="button" @click="i = {{ $k }}" class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 px-3 py-1.5 text-sm hover:border-brand-600 hover:bg-brand-50">
                                    {{ $a->icon() }} {{ $a->file_name }} <span class="text-xs text-slate-400">{{ number_format($a->size / 1024) }}KB</span>
                                </button>
                            </li>
                        @endforeach
                    </ul>

                    {{-- نافذة عرض المرفق --}}
                    <template x-teleport="body">
                        <div x-show="f" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-3 md:p-8" @click.self="i = null" role="dialog" aria-modal="true">
                            <div class="flex h-full max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl" dir="rtl">
                                <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-4 py-2.5">
                                    <div class="min-w-0">
                                        <div class="truncate font-semibold" x-text="f?.name"></div>
                                        <div class="text-xs text-slate-500"><span x-text="f?.size"></span> · <span x-text="(i ?? 0) + 1"></span> من <span x-text="files.length"></span></div>
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" x-show="files.length > 1" :disabled="i === 0" @click="i--" class="btn-secondary px-2.5 py-1.5" title="السابق">→</button>
                                        <button type="button" x-show="files.length > 1" :disabled="i === files.length - 1" @click="i++" class="btn-secondary px-2.5 py-1.5" title="التالي">←</button>
                                        <a :href="f?.view" target="_blank" x-show="f?.view" class="btn-secondary px-2.5 py-1.5" title="فتح في نافذة جديدة">↗</a>
                                        <a :href="f?.download" class="btn-primary px-3 py-1.5">⬇ تنزيل</a>
                                        <button type="button" @click="i = null" class="btn px-2.5 py-1.5 text-slate-500 hover:bg-slate-100" title="إغلاق (Esc)">✕</button>
                                    </div>
                                </div>
                                <div class="flex min-h-0 flex-1 items-center justify-center bg-slate-100">
                                    <template x-if="f?.type === 'pdf'">
                                        <iframe :src="f.view" class="h-full w-full border-0 bg-white" title="معاينة المرفق"></iframe>
                                    </template>
                                    <template x-if="f?.type === 'image'">
                                        <img :src="f.view" :alt="f.name" class="max-h-full max-w-full object-contain p-4">
                                    </template>
                                    <template x-if="f?.type === 'other'">
                                        <div class="p-10 text-center text-slate-600">
                                            <div class="mb-2 text-5xl">📎</div>
                                            <div class="mb-4">لا يمكن معاينة هذا النوع من الملفات داخل النظام.</div>
                                            <a :href="f.download" class="btn-primary">⬇ تنزيل الملف</a>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            @endif

            @if ($letter->replies->isNotEmpty())
                <div class="mt-6 border-t border-slate-100 pt-4">
                    <h3 class="mb-2 text-sm font-semibold text-slate-600">الردود</h3>
                    @foreach ($letter->replies as $r)
                        <a href="{{ route('letters.show', $r) }}" wire:navigate class="block text-sm text-brand-700 underline">{{ $r->outgoing_no ?? 'مسودة' }} — {{ $r->subject }}</a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- سجل الحركة --}}
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">سجل الحركة والتوجيهات</h2>
            <ol class="relative space-y-3 border-r border-slate-200 pr-4">
                @foreach ($letter->events->reject(fn ($e) => $e->action === LetterEvent::VIEWED) as $e)
                    <li class="text-sm">
                        <span class="absolute -right-1.5 mt-1.5 h-3 w-3 rounded-full {{ $e->action === LetterEvent::DIRECTIVE ? 'bg-amber-500' : 'bg-brand-600' }}"></span>
                        <div>
                            <span class="font-medium">{{ $e->actor?->name ?? 'النظام' }}</span>
                            @if ($e->actorUnit) <span class="text-slate-500">({{ $e->actorUnit->name }})</span> @endif
                            — {{ $actionLabels[$e->action] ?? $e->action }}
                            @if ($e->to_status && $e->action === LetterEvent::STATUS_CHANGED)
                                إلى «{{ $statusLabel($e->to_status) }}»
                            @endif
                            @if ($e->action === LetterEvent::REFERRED && ($e->meta['to_unit'] ?? null))
                                إلى «{{ $e->meta['to_unit'] }}»
                            @endif
                            @if ($e->recipient?->unit && in_array($e->action, [LetterEvent::RECEIVED, LetterEvent::STATUS_CHANGED]))
                                <span class="text-slate-500">— {{ $e->recipient->unit->name }}</span>
                            @endif
                            <span class="text-xs text-slate-400">· {{ $e->created_at->format('Y/m/d H:i') }}</span>
                        </div>
                        @if ($e->note)
                            <div @class(['mt-1 rounded-md px-3 py-2', 'bg-amber-50 text-amber-900' => $e->action === LetterEvent::DIRECTIVE, 'bg-slate-50 text-slate-700' => $e->action !== LetterEvent::DIRECTIVE])>{{ $e->note }}</div>
                        @endif
                    </li>
                @endforeach
            </ol>
            <p class="mt-3 text-xs text-slate-400">عدد مرات الفتح: {{ $letter->events->where('action', LetterEvent::VIEWED)->count() }}</p>
        </div>
    </div>

    {{-- الجانب --}}
    <div class="space-y-5">
        <a href="{{ route('letters.print', $letter) }}" target="_blank" class="btn-secondary w-full">🖨 طباعة / حفظ PDF</a>

        @if ($broadcast)
            <div class="card space-y-3 border-amber-300 p-5">
                <h2 class="font-semibold">📢 تعميم</h2>
                @if ($broadcast['target'])
                    <div class="text-sm text-slate-600">موجه إلى: {{ $broadcast['target'] }}</div>
                @endif
                @if ($broadcast['inAudience'])
                    @if ($broadcast['acknowledged'])
                        <div class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800">✓ سجّلت اطلاعك على هذا التعميم.</div>
                    @elseif ($letter->status === \App\Enums\LetterStatus::Sent)
                        <button wire:click="acknowledge" class="btn-primary w-full">علمت</button>
                    @endif
                @endif
                @if ($broadcast['stats'])
                    <div>
                        <div class="mb-1 flex justify-between text-sm">
                            <span>نسبة الاطلاع</span>
                            <span class="font-semibold">{{ $broadcast['stats']['percent'] }}٪ ({{ $broadcast['stats']['read'] }} من {{ $broadcast['stats']['total'] }})</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-amber-500" style="width: {{ $broadcast['stats']['percent'] }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        @endif
        {{-- إجراءات الجهة المستلمة --}}
        @foreach ($myRows as $row)
            <div class="card space-y-3 border-brand-600/30 p-5" wire:key="row-{{ $row->id }}">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">إجراءات {{ $row->unit?->name ?? 'المستلم' }}</h2>
                    @include('partials.recipient-status', ['status' => $row->status])
                </div>
                @if ($row->incoming_no)
                    <div class="text-sm text-slate-600">رقم الوارد: <span class="font-mono" dir="ltr">{{ $row->incoming_no }}</span></div>
                @endif
                @if ($row->role === \App\Enums\RecipientRole::Cc)
                    <div class="text-xs text-slate-500">وصلتكم نسخة للعلم.</div>
                @endif

                @if ($letter->status !== \App\Enums\LetterStatus::Cancelled)
                    <textarea wire:model="statusNote" rows="2" class="input" placeholder="ملاحظة (اختياري)"></textarea>
                    <div class="flex flex-wrap gap-2">
                        @if (in_array($row->status, [RecipientStatus::Pending, RecipientStatus::Received]))
                            <button wire:click="setStatus({{ $row->id }}, 'in_progress')" class="btn-secondary">قيد الإجراء</button>
                        @endif
                        @if (in_array($row->status, [RecipientStatus::Pending, RecipientStatus::Received, RecipientStatus::InProgress]))
                            <button wire:click="setStatus({{ $row->id }}, 'done')" class="btn-primary">مُنجز</button>
                        @endif
                        @if ($row->status !== RecipientStatus::Archived)
                            <button wire:click="setStatus({{ $row->id }}, 'archived')" class="btn-secondary">حفظ في الأرشيف</button>
                        @else
                            <button wire:click="setStatus({{ $row->id }}, 'received')" class="btn-secondary">إخراج من الأرشيف</button>
                        @endif
                    </div>
                    @error('status') <p class="error">{{ $message }}</p> @enderror
                @endif

                @if ($row->referral_note && $row->referredFrom)
                    <div class="rounded-md bg-violet-50 px-3 py-2 text-sm text-violet-900">
                        محال من {{ $row->referredFrom->unit?->name }}: {{ $row->referral_note }}
                    </div>
                @endif

                @if ($letter->status === \App\Enums\LetterStatus::Sent && in_array($row->status, [RecipientStatus::Pending, RecipientStatus::Received, RecipientStatus::InProgress]))
                    <details class="rounded-lg border border-slate-200 p-3">
                        <summary class="cursor-pointer text-sm font-medium text-slate-700">↪ إحالة إلى جهة أخرى</summary>
                        <div class="mt-3 space-y-2">
                            <select wire:model="referUnitId" class="input">
                                <option value="">— اختر الجهة —</option>
                                @if ($referOptions[0]->isNotEmpty())
                                    <optgroup label="الجهات التابعة">
                                        @foreach ($referOptions[0] as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endif
                                <optgroup label="جهات أخرى">
                                    @foreach ($referOptions[1] as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </optgroup>
                            </select>
                            @error('referUnitId') <p class="error">{{ $message }}</p> @enderror
                            <select wire:model="referNote" class="input">
                                @foreach (['للاطلاع والإجراء', 'للإفادة', 'للاطلاع', 'للدراسة وإبداء الرأي', 'للتنفيذ', 'للحفظ'] as $n)
                                    <option>{{ $n }}</option>
                                @endforeach
                            </select>
                            <button wire:click="refer({{ $row->id }})" class="btn-secondary w-full">إحالة</button>
                        </div>
                    </details>
                @endif

                @if ($canReply)
                    <a href="{{ route('letters.create', ['reply_to' => $letter->id]) }}" wire:navigate class="btn-secondary w-full">↩ رد على الخطاب</a>
                @endif
            </div>
        @endforeach

        {{-- المستلمون وحالاتهم --}}
        @unless ($letter->isBroadcast())
        <div class="card p-5">
            <h2 class="mb-3 font-semibold">المستلمون</h2>
            <ul class="space-y-2 text-sm">
                @foreach ($letter->recipients as $r)
                    <li class="flex items-start justify-between gap-2">
                        <div>
                            <div>{{ $r->unit?->name ?? $r->user?->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $r->role->label() }}
                                @if ($r->received_at) · استلم {{ $r->received_at->format('Y/m/d') }} @endif
                                @if ($r->incoming_no) · <span dir="ltr">{{ $r->incoming_no }}</span> @endif
                            </div>
                        </div>
                        @include('partials.recipient-status', ['status' => $r->status])
                    </li>
                @endforeach
            </ul>
        </div>
        @endunless

        {{-- توجيه من جهة أعلى --}}
        @if ($canDirect && $letter->isSent())
            <div class="card space-y-2 p-5">
                <h2 class="font-semibold">إضافة توجيه / تعليق</h2>
                <textarea wire:model="directive" rows="3" class="input" placeholder="مثال: للإفادة بالموقف خلال أسبوع"></textarea>
                @error('directive') <p class="error">{{ $message }}</p> @enderror
                <button wire:click="addDirective" class="btn-secondary w-full">إضافة التوجيه</button>
            </div>
        @endif

        {{-- إلغاء --}}
        @if ($canCancel)
            <div class="card space-y-2 p-5" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="text-sm text-red-600 hover:underline">إلغاء الخطاب…</button>
                <div x-show="open" x-cloak class="space-y-2">
                    <p class="text-xs text-slate-500">الخطاب المرسل لا يُحذف؛ يُلغى مع ذكر السبب ويبقى في السجل.</p>
                    <textarea wire:model="cancelReason" rows="2" class="input" placeholder="سبب الإلغاء"></textarea>
                    @error('cancelReason') <p class="error">{{ $message }}</p> @enderror
                    <button wire:click="cancel" wire:confirm="تأكيد إلغاء الخطاب؟" class="btn-danger w-full">تأكيد الإلغاء</button>
                </div>
            </div>
        @endif
    </div>
</div>
