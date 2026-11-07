<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">المستخدمون</h1>
            <p class="text-sm text-slate-500">الحسابات تُنشأ من قاعدة الموظفين mang (php artisan mang:import-users).</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <input type="search" wire:model.live.debounce.400ms="search" class="input w-60" placeholder="الاسم أو الرقم الوظيفي أو البريد…">
            <select wire:model.live="filter" class="input w-48">
                <option value="">كل المستخدمين</option>
                <option value="unit_users">مستخدمو الجهات</option>
                <option value="admins">ذوو صلاحيات إدارية</option>
                <option value="inactive">الحسابات المعطلة</option>
                @foreach ($this->allRoles as $r)
                    <option value="role:{{ $r->id }}">دور: {{ $r->label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="grid gap-5 lg:grid-cols-5">
        <div class="card overflow-hidden lg:col-span-3">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-xs text-slate-500">
                    <tr><th class="px-3 py-2 text-right">الاسم</th><th class="px-3 py-2 text-right">جهة العمل / التكليف</th><th class="px-3 py-2 text-right">الأدوار</th></tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr wire:key="u-{{ $u->id }}" wire:click="select({{ $u->id }})"
                            @class(['cursor-pointer border-t border-slate-100 hover:bg-slate-50', 'bg-brand-50' => $userId === $u->id, 'opacity-50' => ! $u->is_active])>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $u->name }}</div>
                                <div class="text-xs text-slate-500" dir="ltr">{{ $u->employee_no }} · {{ $u->email }}</div>
                            </td>
                            <td class="px-3 py-2 text-xs">
                                <div>{{ $u->homeUnit?->name ?? '—' }}</div>
                                @foreach ($u->activeAssignments as $a)
                                    <div class="text-brand-700">● {{ $a->unit->name }} @if ($a->isActing()) (إنابة) @endif</div>
                                @endforeach
                            </td>
                            <td class="px-3 py-2">
                                @if ($u->is_admin) <span class="badge bg-red-100 text-red-700">مدير النظام</span> @endif
                                @foreach ($u->roles as $r) <span class="badge bg-brand-50 text-brand-800">{{ $r->label }}</span> @endforeach
                                @unless ($u->is_active) <span class="badge bg-slate-200 text-slate-600">معطل</span> @endunless
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="p-3">{{ $users->links() }}</div>
        </div>

        <div class="lg:col-span-2">
            @if ($u = $this->selected)
                <form wire:submit="save" class="card sticky top-4 space-y-4 p-5">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $u->name }}</h2>
                        <div class="text-sm text-slate-500">{{ $u->job_title }} · {{ $u->homeUnit?->name }}</div>
                        <div class="text-xs text-slate-400" dir="ltr">{{ $u->employee_no }} · {{ $u->email }}</div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" wire:model="isActive" class="rounded border-slate-300"> الحساب نشط (يستطيع الدخول)
                    </label>
                    @if (auth()->user()->is_admin)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" wire:model="isAdmin" class="rounded border-slate-300"> مدير النظام (كل الصلاحيات الإدارية)
                        </label>
                    @endif
                    @error('isActive') <p class="error">{{ $message }}</p> @enderror

                    <div>
                        <div class="label">الأدوار</div>
                        @forelse ($this->allRoles as $r)
                            <label class="flex items-center gap-2 py-0.5 text-sm">
                                <input type="checkbox" wire:model="roleIds" value="{{ $r->id }}" class="rounded border-slate-300"> {{ $r->label }}
                            </label>
                        @empty
                            <p class="text-xs text-slate-500">لا توجد أدوار.</p>
                        @endforelse
                    </div>

                    <div>
                        <label class="label">كلمة مرور جديدة (اختياري)</label>
                        <input type="text" wire:model="newPassword" class="input" dir="ltr" autocomplete="off" placeholder="8 أحرف على الأقل">
                        @error('newPassword') <p class="error">{{ $message }}</p> @enderror
                    </div>

                    <div class="rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                        التكليف بالجهات يتم من <a href="{{ route('admin.assignments') }}" wire:navigate class="text-brand-700 underline">شاشة التكليفات</a>.
                    </div>

                    <button type="submit" class="btn-primary w-full">حفظ</button>
                </form>
            @else
                <div class="card px-6 py-16 text-center text-slate-500">اختر مستخدمًا لعرض حسابه وأدواره.</div>
            @endif
        </div>
    </div>
</div>
