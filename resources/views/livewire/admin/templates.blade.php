<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">نماذج الخطابات</h1>
            <p class="text-sm text-slate-500">النموذج = نص ثابت + حقول بين قوسين مزدوجين مثل <span dir="ltr">@{{ نوع_الإجازة }}</span>. تعديل نموذج مستخدَم ينشئ إصدارًا جديدًا.</p>
        </div>
        <button wire:click="create" class="btn-primary">+ نموذج جديد</button>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="card overflow-hidden lg:col-span-2">
            @foreach ($this->templates->groupBy(fn ($t) => $t->category ?: 'عام') as $cat => $items)
                <div class="bg-slate-50 px-4 py-1.5 text-xs font-semibold text-slate-500">{{ $cat }}</div>
                @foreach ($items as $t)
                    <div wire:key="t-{{ $t->id }}" @class(['flex items-center justify-between gap-2 border-b border-slate-100 px-4 py-2.5 text-sm', 'bg-brand-50' => $templateId === $t->id && $editing, 'opacity-50' => ! $t->is_active])>
                        <button wire:click="edit({{ $t->id }})" class="min-w-0 flex-1 text-right">
                            <div class="truncate font-medium">{{ $t->name }}</div>
                            <div class="text-xs text-slate-500">{{ $t->allowed_for->label() }} · إصدار {{ $t->version }} · استُخدم {{ $t->letters_count }} مرة</div>
                        </button>
                        <button wire:click="toggle({{ $t->id }})" class="shrink-0 text-xs {{ $t->is_active ? 'text-slate-500' : 'text-brand-700' }} hover:underline">{{ $t->is_active ? 'تعطيل' : 'تفعيل' }}</button>
                    </div>
                @endforeach
            @endforeach
        </div>

        <div class="lg:col-span-3">
            @if ($editing)
                <form wire:submit="save" class="card space-y-4 p-5">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="label">اسم النموذج *</label>
                            <input type="text" wire:model="name" class="input">
                            @error('name') <p class="error">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="label">التصنيف</label>
                            <input type="text" wire:model="category" class="input" placeholder="الموارد البشرية، عام…">
                        </div>
                        <div>
                            <label class="label">متاح لـ</label>
                            <select wire:model="allowed_for" class="input">
                                @foreach (\App\Enums\TemplateAudience::cases() as $a)
                                    <option value="{{ $a->value }}">{{ $a->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">الجهة المستلمة الافتراضية</label>
                            <select wire:model="default_recipient_unit_id" class="input">
                                <option value="">— لا يوجد —</option>
                                @foreach ($this->units as $u)
                                    <option value="{{ $u->id }}">{{ str_repeat('— ', $u->depth) }}{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="label">قالب الموضوع</label>
                        <input type="text" wire:model.live.debounce.500ms="subject_pattern" class="input" placeholder="طلب إجازة @{{ نوع_الإجازة }}">
                    </div>
                    <div>
                        <label class="label">نص النموذج *</label>
                        <textarea wire:model.live.debounce.700ms="body" rows="12" class="input font-mono leading-7"></textarea>
                        @error('body') <p class="error">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">حقول تُملأ تلقائيًا:
                            @foreach (array_keys(\App\Livewire\Admin\Templates::AUTO) as $a) <code class="rounded bg-slate-100 px-1" dir="ltr">&#123;&#123; {{ $a }} &#125;&#125;</code> @endforeach
                        </p>
                    </div>

                    @if ($fields)
                        <div>
                            <div class="label">الحقول المتغيرة ({{ count($fields) }})</div>
                            <div class="overflow-hidden rounded-lg border border-slate-200">
                                <table class="w-full text-sm">
                                    <thead class="bg-slate-50 text-xs text-slate-500"><tr><th class="px-2 py-1.5 text-right">الحقل</th><th class="px-2 py-1.5 text-right">العنوان</th><th class="px-2 py-1.5">النوع</th><th class="px-2 py-1.5">إلزامي</th></tr></thead>
                                    <tbody>
                                        @foreach ($fields as $i => $f)
                                            <tr wire:key="f-{{ $f['name'] }}" class="border-t border-slate-100">
                                                <td class="px-2 py-1.5 font-mono text-xs">{{ $f['name'] }}</td>
                                                <td class="px-2 py-1.5"><input type="text" wire:model="fields.{{ $i }}.label" class="input py-1"></td>
                                                <td class="px-2 py-1.5">
                                                    <select wire:model.live="fields.{{ $i }}.type" class="input py-1">
                                                        @foreach (\App\Livewire\Admin\Templates::TYPES as $k => $label) <option value="{{ $k }}">{{ $label }}</option> @endforeach
                                                    </select>
                                                    @if ($f['type'] === 'select')
                                                        <input type="text" wire:model="fields.{{ $i }}.options" class="input mt-1 py-1 text-xs" placeholder="الخيارات مفصولة بفواصل">
                                                    @endif
                                                </td>
                                                <td class="px-2 py-1.5 text-center"><input type="checkbox" wire:model="fields.{{ $i }}.required" class="rounded border-slate-300"></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div>
                        <div class="label">معاينة</div>
                        <div class="whitespace-pre-line rounded-lg border border-dashed border-slate-300 bg-slate-50 p-4 text-sm leading-7">{{ $preview }}</div>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary">حفظ</button>
                        <button type="button" wire:click="$set('editing', false)" class="btn-secondary">إغلاق</button>
                    </div>
                </form>
            @else
                <div class="card px-6 py-16 text-center text-slate-500">اختر نموذجًا لتعديله، أو أنشئ نموذجًا جديدًا.</div>
            @endif
        </div>
    </div>
</div>
