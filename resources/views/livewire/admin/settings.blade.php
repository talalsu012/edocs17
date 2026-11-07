<div>
    <div class="mb-5">
        <h1 class="text-2xl font-bold text-slate-800">إعدادات الجامعة</h1>
        <p class="text-sm text-slate-500">تظهر في الترويسة والطباعة وأرقام الخطابات وشاشات النظام.</p>
    </div>

    <form wire:submit="save" class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <div class="card space-y-4 p-5">
                <h2 class="font-semibold">الهوية</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">اسم الجامعة (عربي) *</label>
                        <input type="text" wire:model="university_name_ar" class="input">
                        @error('university_name_ar') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">اسم الجامعة (إنجليزي)</label>
                        <input type="text" wire:model="university_name_en" class="input" dir="ltr">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">اسم النظام *</label>
                        <input type="text" wire:model="system_name" class="input">
                        @error('system_name') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                        @if ($logo)
                            <img src="{{ $logo->temporaryUrl() }}" class="max-h-full">
                        @elseif ($this->logoUrl)
                            <img src="{{ $this->logoUrl }}" class="max-h-full">
                        @else
                            <span class="text-xs text-slate-400">بلا شعار</span>
                        @endif
                    </div>
                    <div class="space-y-1">
                        <label class="label">شعار الجامعة (PNG/JPG/SVG — حتى 2MB)</label>
                        <input type="file" wire:model="logo" accept="image/*" class="text-sm">
                        @error('logo') <p class="error">{{ $message }}</p> @enderror
                        @if ($this->logoUrl)
                            <button type="button" wire:click="removeLogo" wire:confirm="حذف الشعار؟" class="text-xs text-red-600">حذف الشعار</button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="card space-y-4 p-5">
                <h2 class="font-semibold">الترويسة والتذييل</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label class="label">العنوان</label>
                        <input type="text" wire:model="letterhead_address" class="input">
                    </div>
                    <div>
                        <label class="label">الهاتف</label>
                        <input type="text" wire:model="letterhead_phone" class="input" dir="ltr">
                    </div>
                    <div>
                        <label class="label">البريد الإلكتروني</label>
                        <input type="email" wire:model="letterhead_email" class="input" dir="ltr">
                        @error('letterhead_email') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">الموقع الإلكتروني</label>
                        <input type="text" wire:model="letterhead_website" class="input" dir="ltr">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model="show_hijri" class="rounded border-slate-300"> إظهار التاريخ الهجري في الطباعة
                </label>
            </div>

            <div class="card space-y-4 p-5">
                <h2 class="font-semibold">الترقيم والمرفقات والتنبيهات</h2>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div>
                        <label class="label">بادئة الترقيم *</label>
                        <input type="text" wire:model.live="number_prefix" class="input">
                        <p class="mt-1 text-xs text-slate-500" dir="ltr">{{ $number_prefix }}/م ب ش/{{ now()->year }}/0153</p>
                        @error('number_prefix') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">حجم المرفق الأقصى (MB)</label>
                        <input type="number" wire:model="max_attachment_mb" class="input" min="1" max="100">
                    </div>
                    <div>
                        <label class="label">التنبيه قبل موعد الرد (أيام)</label>
                        <input type="number" wire:model="reminder_days_before" class="input" min="0" max="30">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="label">أنواع الملفات المسموحة (مفصولة بفواصل)</label>
                        <input type="text" wire:model="allowed_extensions" class="input" dir="ltr">
                        @error('allowed_extensions') <p class="error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">تغيير البادئة يؤثر على الخطابات الجديدة فقط؛ أرقام الخطابات المرسلة سابقًا لا تتغير.</p>
            </div>
        </div>

        <div class="space-y-5">
            <div class="card space-y-3 p-5">
                <h2 class="font-semibold">الجهات التي يراسلها الموظف</h2>
                <p class="text-xs text-slate-500">الجهات التي يحق للموظف العادي إرسال طلبات شخصية إليها.</p>
                <input type="search" wire:model.live.debounce.300ms="unitFilter" class="input" placeholder="ابحث…">
                <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-200">
                    @foreach ($this->units as $u)
                        <label wire:key="st-{{ $u->id }}" class="flex items-center gap-2 border-b border-slate-50 px-3 py-1.5 text-sm">
                            <input type="checkbox" wire:model="staffTargets" value="{{ $u->id }}" class="rounded border-slate-300">
                            <span style="padding-right: {{ trim($unitFilter) ? 0 : $u->depth * 10 }}px">{{ $u->name }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="text-xs text-slate-500">المحدد: {{ count($staffTargets) }}</div>
            </div>

            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">حفظ الإعدادات</button>

            @can('training.manage')
                <div class="card space-y-3 border-sky-200 p-5">
                    <h2 class="font-semibold">🎓 بيانات التدريب</h2>
                    <p class="text-xs text-slate-500">خطابات واقعية بكل الأنواع والحالات ومرفقات، موزعة على آخر 60 يومًا، لتدريب المستخدمين. تُحذف دفعة واحدة قبل التشغيل الفعلي.</p>
                    <div class="text-sm">الموجود حاليًا: <strong>{{ $this->trainingCount }}</strong> خطابًا</div>
                    @error('training') <p class="error">{{ $message }}</p> @enderror
                    <div class="flex gap-2">
                        <button type="button" wire:click="generateTraining" wire:loading.attr="disabled" class="btn-secondary flex-1">
                            <span wire:loading.remove wire:target="generateTraining">إنشاء</span>
                            <span wire:loading wire:target="generateTraining">جارٍ الإنشاء…</span>
                        </button>
                        @if ($this->trainingCount)
                            <button type="button" wire:click="purgeTraining" wire:confirm="حذف كل خطابات التدريب ومرفقاتها نهائيًا؟" class="btn text-red-600 hover:bg-red-50">حذف الكل</button>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </form>
</div>
