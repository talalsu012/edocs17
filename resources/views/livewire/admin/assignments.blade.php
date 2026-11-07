<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">التكليفات والتنقلات</h1>
            <p class="text-sm text-slate-500">الصندوق ملك الجهة؛ تغيير الشاغل لا ينقل خطابًا ولا يحذفه.</p>
        </div>
        @if ($this->vacantCount)
            <button wire:click="$toggle('onlyVacant')" @class(['badge px-3 py-1 text-sm', 'bg-red-600 text-white' => $onlyVacant, 'bg-red-100 text-red-700' => ! $onlyVacant])>
                ⚠ {{ $this->vacantCount }} جهة بلا مستخدم {{ $onlyVacant ? '(عرض الكل)' : '' }}
            </button>
        @endif
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        {{-- شجرة الجهات --}}
        <div class="card overflow-hidden lg:col-span-2">
            <div class="border-b border-slate-100 p-3">
                <input type="search" wire:model.live.debounce.300ms="unitSearch" class="input" placeholder="ابحث عن جهة بالاسم أو الرمز…">
            </div>
            <div class="max-h-[70vh] overflow-y-auto">
                @foreach ($this->units as $u)
                    @php($holder = $u->activeAssignment)
                    <button wire:click="selectUnit({{ $u->id }})" wire:key="unit-{{ $u->id }}"
                        @class(['flex w-full items-center justify-between gap-2 border-b border-slate-50 px-3 py-2 text-right text-sm hover:bg-slate-50',
                                'bg-brand-50' => $unitId === $u->id, 'opacity-50' => ! $u->is_active])>
                        <span class="min-w-0" style="padding-right: {{ trim($unitSearch) || $onlyVacant ? 0 : $u->depth * 14 }}px">
                            <span class="block truncate font-medium">{{ $u->name }}</span>
                            <span class="block truncate text-xs text-slate-500">
                                @if ($holder) {{ $holder->user->name }} @if ($holder->isActing()) <span class="text-amber-700">(بالإنابة)</span> @endif
                                @else <span class="text-red-600">بلا مستخدم</span> @endif
                            </span>
                        </span>
                        <span class="shrink-0 text-xs text-slate-400">{{ $u->code }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- تفاصيل الجهة --}}
        <div class="space-y-5 lg:col-span-3">
            @if (! $this->unit)
                <div class="card px-6 py-16 text-center text-slate-500">اختر جهة من القائمة لعرض شاغلها وسجل تكليفاتها.</div>
            @else
                @php($unit = $this->unit)
                <div class="card p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold">{{ $unit->name }}</h2>
                            <div class="text-sm text-slate-500">{{ $unit->type->label() }} · الرمز {{ $unit->code }} @if ($unit->parent) · تتبع {{ $unit->parent->name }} @endif
                                @if ($unit->isLinkedToMang()) · <span class="text-sky-700">مرتبطة بـ mang</span> @endif</div>
                        </div>
                        <div class="text-left text-sm">
                            <div class="text-slate-500">{{ $unit->head_title ?? 'المستخدم' }} الحالي</div>
                            <div class="font-semibold">{{ $unit->activeAssignment?->user->name ?? '—' }}</div>
                        </div>
                    </div>

                    @if ($this->handover)
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                            @foreach (['pending' => 'معلّق', 'in_progress' => 'قيد الإجراء', 'overdue' => 'متأخر', 'drafts' => 'مسودات'] as $k => $label)
                                <div @class(['rounded-lg px-3 py-2 text-center', 'bg-red-50 text-red-700' => $k === 'overdue' && $this->handover[$k], 'bg-slate-50' => ! ($k === 'overdue' && $this->handover[$k])])>
                                    <div class="text-xl font-bold">{{ $this->handover[$k] }}</div>
                                    <div class="text-xs">{{ $label }}</div>
                                </div>
                            @endforeach
                        </div>
                        <a href="{{ route('admin.handover', $unit) }}" target="_blank" class="mt-3 inline-block text-sm text-brand-700 underline">🖨 تقرير التسليم والتسلّم</a>
                    @endif
                </div>

                {{-- تكليف جديد --}}
                <form wire:submit="assign" class="card space-y-4 p-5">
                    <h3 class="font-semibold">تكليف جديد</h3>
                    <div class="relative">
                        <label class="label">المستخدم (بالاسم أو الرقم الوظيفي)</label>
                        <input type="search" wire:model.live.debounce.300ms="userSearch" class="input" placeholder="اكتب حرفين على الأقل…" @if ($userId) readonly @endif>
                        @if ($userId)
                            <button type="button" wire:click="$set('userId', null)" class="absolute left-2 top-8 text-xs text-slate-500">تغيير</button>
                        @elseif ($this->userResults->isNotEmpty())
                            <ul class="absolute z-10 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                                @foreach ($this->userResults as $r)
                                    <li><button type="button" wire:click="pickUser({{ $r->id }})" class="w-full px-3 py-2 text-right text-sm hover:bg-slate-50">
                                        {{ $r->name }} <span class="text-xs text-slate-500">— {{ $r->employee_no }} · {{ $r->homeUnit?->name }}</span>
                                    </button></li>
                                @endforeach
                            </ul>
                        @endif
                        @error('userId') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">نوع التكليف</label>
                            <select wire:model.live="kind" class="input">
                                <option value="original">أصيل</option>
                                <option value="acting">بالإنابة (مؤقت)</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">رقم القرار</label>
                            <input type="text" wire:model="decisionNo" class="input">
                        </div>
                        <div>
                            <label class="label">من تاريخ</label>
                            <input type="date" wire:model="startDate" class="input">
                            @error('startDate') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">إلى تاريخ @if ($kind === 'acting') <span class="text-red-500">*</span> @else <span class="text-xs text-slate-400">(اختياري)</span> @endif</label>
                            <input type="date" wire:model="endDate" class="input">
                            @error('endDate') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">تاريخ القرار</label>
                            <input type="date" wire:model="decisionDate" class="input">
                        </div>
                    </div>

                    @if ($kind === 'original')
                        <label class="flex items-start gap-2 text-sm">
                            <input type="checkbox" wire:model="endOthers" class="mt-1 rounded border-slate-300">
                            <span>إنهاء تكليفات المستخدم الأصيلة في الجهات الأخرى (نقل)
                                <span class="block text-xs text-slate-500">ويُنهى تلقائيًا تكليف الأصيل الحالي لهذه الجهة؛ يرث الجديد الوارد والصادر والأرشيف فورًا.</span></span>
                        </label>
                    @else
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">الإنابة تتقدم على الأصيل خلال فترتها وتنتهي تلقائيًا، وتُوسَم خطاباتها بـ«بالإنابة».</p>
                    @endif

                    <button type="submit" class="btn-primary">حفظ التكليف</button>
                </form>

                {{-- السجل --}}
                <div class="card p-5">
                    <h3 class="mb-3 font-semibold">سجل التكليفات</h3>
                    @forelse ($this->history as $a)
                        <div wire:key="a-{{ $a->id }}" class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-2 text-sm last:border-0">
                            <div>
                                <span class="font-medium">{{ $a->user->name }}</span>
                                <span class="badge {{ $a->isActing() ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700' }}">{{ $a->kind->label() }}</span>
                                @if ($a->isActive()) <span class="badge bg-emerald-100 text-emerald-800">ساري</span> @endif
                                <div class="text-xs text-slate-500">
                                    {{ $a->start_date->format('Y/m/d') }} ← {{ $a->end_date?->format('Y/m/d') ?? 'مستمر' }}
                                    @if ($a->decision_no) · قرار {{ $a->decision_no }} @endif
                                    @if ($a->assigner) · بواسطة {{ $a->assigner->name }} @endif
                                </div>
                            </div>
                            @if ($a->isActive())
                                <button wire:click="endAssignment({{ $a->id }})" wire:confirm="إنهاء تكليف {{ $a->user->name }} اعتبارًا من اليوم؟" class="text-xs text-red-600 hover:underline">إنهاء التكليف</button>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-500">لا توجد تكليفات لهذه الجهة.</p>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</div>
