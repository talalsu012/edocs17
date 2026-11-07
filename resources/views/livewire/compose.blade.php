<div>
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-slate-800">{{ $letterId ? 'تعديل مسودة' : ($mode === 'broadcast' ? 'تعميم جديد' : 'خطاب جديد') }}</h1>
        <a href="{{ route('mail.box', 'drafts') }}" wire:navigate class="text-sm text-slate-500 hover:text-slate-700">المسودات ←</a>
    </div>

    <form wire:submit="send" class="grid gap-5 lg:grid-cols-3">
        <div class="card space-y-4 p-5 lg:col-span-2">
            {{-- صفة الإرسال --}}
            @if ($this->myUnits->isNotEmpty())
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="mode" value="unit"> باسم الجهة
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="mode" value="broadcast"> تعميم
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" wire:model.live="mode" value="personal"> طلب شخصي (باسمي كموظف)
                    </label>
                </div>
            @endif

            @if ($mode !== 'personal')
                <div>
                    <label class="label">الجهة المرسلة</label>
                    <select wire:model.live="unitId" class="input" @disabled($this->myUnits->count() < 2)>
                        @foreach ($this->myUnits as $u)
                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                        @endforeach
                    </select>
                    @error('unitId') <p class="error">{{ $message }}</p> @enderror
                </div>
            @else
                <div class="rounded-lg bg-sky-50 px-3 py-2 text-xs text-sky-800">
                    الطلب الشخصي يُرسل باسمك، ويطّلع عليه مديرك المباشر ومن فوقه، ويُوجَّه فقط للجهات المسموح بها.
                </div>
            @endif

            <div>
                <label class="label">النموذج (اختياري)</label>
                <select wire:model.live="templateId" class="input">
                    <option value="">— خطاب فارغ —</option>
                    @foreach ($this->templates->groupBy('category') as $cat => $items)
                        <optgroup label="{{ $cat }}">
                            @foreach ($items as $t)
                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="label">الموضوع <span class="text-red-500">*</span></label>
                <input type="text" wire:model="subject" class="input">
                @error('subject') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">النص</label>
                <textarea wire:model="body" rows="14" class="input leading-7"></textarea>
                <p class="mt-1 text-xs text-slate-500">ما بين [ ] حقول من النموذج تحتاج إلى تعبئة.</p>
                @error('body') <p class="error">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label">المرفقات ({{ implode('، ', \App\Models\Setting::get('allowed_extensions', [])) }} — حتى {{ \App\Models\Setting::get('max_attachment_mb') }}MB للملف)</label>
                <input type="file" wire:model="files" multiple class="block w-full text-sm text-slate-600 file:ml-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-brand-700">
                <div wire:loading wire:target="files" class="mt-1 text-xs text-slate-500">جارٍ الرفع…</div>
                @error('files.*') <p class="error">{{ $message }}</p> @enderror
                @if ($this->existingAttachments->isNotEmpty())
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($this->existingAttachments as $a)
                            <li>📎 {{ $a->file_name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="space-y-5">
            @if ($mode === 'broadcast')
            <div class="card space-y-4 p-5">
                <h2 class="font-semibold">نطاق التعميم</h2>
                <div class="space-y-2">
                    @foreach ($this->broadcastScopes as $scope)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="radio" wire:model.live="broadcastScope" value="{{ $scope->value }}"> {{ $scope->label() }}
                        </label>
                    @endforeach
                </div>
                @if (count($this->broadcastScopes) === 1)
                    <p class="text-xs text-slate-500">جهتك مخوّلة بالتعميم على فرعها فقط (الجهة وما تحتها).</p>
                @endif

                @if ($broadcastScope === 'subtree')
                    <div>
                        <label class="label">الفرع المستهدف</label>
                        <select wire:model="broadcastRoot" class="input">
                            @foreach ($this->broadcastRoots as $u)
                                <option value="{{ $u->id }}">{{ str_repeat('— ', max(0, $u->depth - ($this->broadcastRoots->first()->depth ?? 0))) }}{{ $u->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-slate-500">يصل لمستخدمي الجهة وكل ما تحتها وموظفيها.</p>
                        @error('broadcastRoot') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @elseif ($broadcastScope === 'unit_type')
                    <div>
                        <label class="label">نوع الجهات</label>
                        <select wire:model="broadcastType" class="input">
                            <option value="">— اختر —</option>
                            @foreach (\App\Enums\UnitType::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->label() }}</option>
                            @endforeach
                        </select>
                        @error('broadcastType') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif
                <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">التعميم لا يتطلب ردًا؛ المطلوب من المستهدفين الضغط على «علمت»، وتظهر لك نسبة الاطلاع.</div>
            </div>
            @else
            <div class="card space-y-3 p-5">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">المستلمون</h2>
                    <span class="text-xs text-slate-500">إلى: {{ count($to) }} @if ($mode === 'unit') · نسخة: {{ count($cc) }} @endif</span>
                </div>
                <input type="search" wire:model.live.debounce.300ms="unitFilter" class="input" placeholder="ابحث عن جهة…">
                @error('to') <p class="error">{{ $message }}</p> @enderror

                <div class="max-h-80 overflow-y-auto rounded-lg border border-slate-200">
                    <div class="sticky top-0 grid grid-cols-[1fr_auto_auto] gap-2 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-500">
                        <span>الجهة</span><span>إلى</span>@if ($mode === 'unit')<span>نسخة</span>@else<span></span>@endif
                    </div>
                    @forelse ($this->recipientOptions as $u)
                        <div wire:key="u-{{ $u->id }}" class="grid grid-cols-[1fr_auto_auto] items-center gap-2 border-t border-slate-100 px-3 py-1.5 text-sm">
                            <span class="truncate" style="padding-right: {{ $unitFilter ? 0 : $u->depth * 12 }}px">{{ $u->name }}</span>
                            <input type="checkbox" wire:model.live="to" value="{{ $u->id }}" class="rounded border-slate-300">
                            @if ($mode === 'unit')
                                <input type="checkbox" wire:model.live="cc" value="{{ $u->id }}" class="rounded border-slate-300" @disabled(in_array($u->id, $to))>
                            @else
                                <span></span>
                            @endif
                        </div>
                    @empty
                        <div class="px-3 py-6 text-center text-sm text-slate-500">لا توجد جهات مطابقة.</div>
                    @endforelse
                </div>
            </div>
            @endif

            <div class="card space-y-4 p-5">
                <div>
                    <label class="label">درجة الأهمية</label>
                    <select wire:model="priority" class="input">
                        @foreach (\App\Enums\Priority::cases() as $p)
                            <option value="{{ $p->value }}">{{ $p->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($mode !== 'broadcast')
                <div>
                    <label class="label">موعد الرد المطلوب</label>
                    <input type="date" wire:model="dueDate" class="input">
                    @error('dueDate') <p class="error">{{ $message }}</p> @enderror
                </div>
                @endif
                @if ($mode === 'unit')
                    <label class="flex items-start gap-2 text-sm">
                        <input type="checkbox" wire:model="isConfidential" class="mt-1 rounded border-slate-300">
                        <span>سري <span class="block text-xs text-slate-500">يراه المرسل والمستلم والمدير المباشر فقط، دون الجهات الأعلى.</span></span>
                    </label>
                @endif
            </div>

            <div class="flex flex-col gap-2">
                <button type="submit" class="btn-primary" wire:loading.attr="disabled"
                        wire:confirm="سيُرسل الخطاب ويأخذ رقم صادر ولا يمكن حذفه بعد الإرسال. متابعة؟">
                    إرسال
                </button>
                <button type="button" wire:click="saveDraft" class="btn-secondary" wire:loading.attr="disabled">حفظ كمسودة</button>
                @if ($letterId)
                    <button type="button" wire:click="discard" wire:confirm="حذف المسودة نهائيًا؟" class="btn text-red-600 hover:bg-red-50">حذف المسودة</button>
                @endif
            </div>
        </div>
    </form>
</div>
