<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">الأدوار والصلاحيات</h1>
            <p class="text-sm text-slate-500">صلاحيات إدارية فقط؛ الإرسال باسم الجهات والرؤية الهرمية تحددهما التكليفات.</p>
        </div>
        <button wire:click="create" class="btn-primary">+ دور جديد</button>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="space-y-3 lg:col-span-3">
            @foreach ($this->roles as $role)
                <div wire:key="role-{{ $role->id }}" @class(['card p-4', 'ring-2 ring-brand-600' => $roleId === $role->id && $editing])>
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <div class="font-semibold">{{ $role->label }}
                                @if ($role->is_system) <span class="badge bg-slate-100 text-slate-600">نظام</span> @endif
                            </div>
                            <div class="text-xs text-slate-500">{{ $role->description }}</div>
                        </div>
                        <div class="flex items-center gap-3 text-sm">
                            <a href="{{ route('admin.users', ['filter' => 'role:'.$role->id]) }}" wire:navigate class="text-slate-500 hover:underline">{{ $role->users_count }} مستخدم</a>
                            <button wire:click="edit({{ $role->id }})" class="text-brand-700 hover:underline">تعديل</button>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @forelse ($role->permissions() as $p)
                            <span class="badge bg-brand-50 text-brand-800">{{ \App\Enums\Permission::tryFrom($p)?->label() ?? $p }}</span>
                        @empty
                            <span class="text-xs text-slate-400">بلا صلاحيات</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        <div class="lg:col-span-2">
            @if ($editing)
                <form wire:submit="save" class="card sticky top-4 space-y-4 p-5">
                    <h2 class="font-semibold">{{ $roleId ? 'تعديل الدور' : 'دور جديد' }}</h2>
                    <div>
                        <label class="label">اسم الدور *</label>
                        <input type="text" wire:model="label" class="input" placeholder="مثال: مسؤول التقارير">
                        @error('label') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">المعرّف (لاتيني)</label>
                        <input type="text" wire:model="name" class="input" dir="ltr" placeholder="reports_officer" @disabled($roleId && $this->roles->firstWhere('id', $roleId)?->is_system)>
                        @error('name') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="label">الوصف</label>
                        <textarea wire:model="description" rows="2" class="input"></textarea>
                    </div>
                    <div class="space-y-3">
                        <div class="label">الصلاحيات</div>
                        @foreach ($groups as $group => $perms)
                            <div>
                                <div class="mb-1 text-xs font-semibold text-slate-500">{{ $group }}</div>
                                @foreach ($perms as $p)
                                    <label class="flex items-center gap-2 py-0.5 text-sm">
                                        <input type="checkbox" wire:model="permissions" value="{{ $p->value }}" class="rounded border-slate-300">
                                        {{ $p->label() }}
                                    </label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="btn-primary flex-1">حفظ</button>
                        <button type="button" wire:click="$set('editing', false)" class="btn-secondary">إلغاء</button>
                    </div>
                    @if ($roleId && ! $this->roles->firstWhere('id', $roleId)?->is_system)
                        <button type="button" wire:click="delete({{ $roleId }})" wire:confirm="حذف هذا الدور؟ سيُسحب من كل المستخدمين." class="text-sm text-red-600 hover:underline">حذف الدور</button>
                    @endif
                </form>
            @else
                <div class="card p-5 text-sm leading-7 text-slate-600">
                    <h2 class="mb-2 font-semibold text-slate-800">كيف تعمل الصلاحيات؟</h2>
                    <ul class="list-inside list-disc space-y-1">
                        <li><strong>مستخدم الجهة</strong>: يحدده التكليف (من شاشة التكليفات)، ويمنحه الإرسال باسم الجهة ورؤية ما تحتها.</li>
                        <li><strong>الموظف العادي</strong>: كل حساب نشط؛ يرسل طلبات شخصية للجهات المسموحة.</li>
                        <li><strong>الأدوار</strong>: صلاحيات إدارية إضافية تُمنح لأي مستخدم، ولا تمنح قراءة المراسلات.</li>
                        <li><strong>مدير النظام</strong>: يملك كل الصلاحيات الإدارية.</li>
                    </ul>
                </div>
            @endif
        </div>
    </div>
</div>
