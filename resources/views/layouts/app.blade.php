@php
    $user = auth()->user();
    $activeUnit = \App\Support\ActiveUnit::get($user);
    $mailbox = app(\App\Services\Mailbox::class);
    $unread = $mailbox->unreadCount($user, $activeUnit);
    $boxIcons = ['inbox' => '📥', 'outbox' => '📤', 'drafts' => '📝', 'broadcasts' => '📢', 'following' => '👁', 'archive' => '🗄'];
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'المراسلات' }} — {{ \App\Models\Setting::get('system_name') }} · {{ \App\Models\Setting::get('university_name_ar') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-800 antialiased">
<div class="flex min-h-screen">
    {{-- الشريط الجانبي --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-brand-800 text-brand-50 md:flex">
        <div class="border-b border-white/10 px-5 py-5">
            <div class="text-lg font-bold">{{ \App\Models\Setting::get('university_name_ar') }}</div>
            <div class="text-xs text-brand-100/80">{{ \App\Models\Setting::get('system_name') }}</div>
        </div>

        <div class="px-4 pt-4">
            <a href="{{ route('letters.create') }}" wire:navigate class="btn w-full bg-white text-brand-800 hover:bg-brand-50">
                ✉️ خطاب جديد
            </a>
            @if ($activeUnit)
                <a href="{{ route('letters.create', ['mode' => 'broadcast']) }}" wire:navigate class="mt-2 block text-center text-xs text-brand-100/90 hover:text-white">📢 تعميم جديد</a>
            @endif
        </div>

        <div class="px-4 pt-3">
            <livewire:notification-bell />
        </div>

        <nav class="flex-1 space-y-1 px-3 py-4">
            @foreach (\App\Services\Mailbox::BOXES as $key => $label)
                @continue($key === 'following' && ! $activeUnit)
                <a href="{{ route('mail.box', $key) }}" wire:navigate
                   @class([
                       'flex items-center justify-between rounded-lg px-3 py-2 text-sm transition',
                       'bg-white/15 font-semibold text-white' => request()->routeIs('mail.box') && request()->route('box') === $key,
                       'text-brand-50/90 hover:bg-white/10' => ! (request()->routeIs('mail.box') && request()->route('box') === $key),
                   ])>
                    <span>{{ $boxIcons[$key] }} {{ $label }}</span>
                    @if ($key === 'inbox' && $unread)
                        <span class="badge bg-amber-400 text-amber-950">{{ $unread }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="space-y-3 border-t border-white/10 px-4 py-4 text-sm">
            <div>
                <div class="font-semibold">{{ $user->displayName() }}</div>
                <div class="text-xs text-brand-100/80">{{ $user->displayJobTitle() }}</div>
            </div>

            @if ($user->currentUnits()->count() > 1)
                <form method="POST" action="{{ route('active-unit') }}">
                    @csrf
                    <label class="mb-1 block text-xs text-brand-100/80">أعمل باسم</label>
                    <select name="unit_id" onchange="this.form.submit()" class="w-full rounded-md border-0 bg-white/10 px-2 py-1.5 text-sm text-white">
                        @foreach ($user->currentUnits() as $u)
                            <option value="{{ $u->id }}" class="text-slate-800" @selected($u->id === $activeUnit?->id)>{{ $u->name }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif ($activeUnit)
                <div class="rounded-md bg-white/10 px-2 py-1.5 text-xs">
                    مستخدم جهة: <span class="font-semibold">{{ $activeUnit->name }}</span>
                </div>
            @else
                <div class="rounded-md bg-white/10 px-2 py-1.5 text-xs">موظف — {{ $user->homeUnit?->name ?? 'بلا جهة' }}</div>
            @endif

            @if ($activeUnit || $user->hasPermission('reports.view'))
                <a href="{{ route('dashboard') }}" wire:navigate @class(['block rounded-md bg-white/10 px-2 py-1.5 text-xs hover:bg-white/20', 'bg-white/20' => request()->routeIs('dashboard')])>📊 لوحة المؤشرات</a>
            @endif

            @can('admin.any')
                <div class="space-y-1 rounded-md bg-white/5 p-2">
                    <div class="text-[11px] text-brand-100/70">الإدارة</div>
                    @foreach ([
                        ['assignments.manage', 'admin.assignments', '🏛 التكليفات والتنقلات'],
                        ['users.manage', 'admin.users', '👥 المستخدمون'],
                        ['roles.manage', 'admin.roles', '🔐 الأدوار والصلاحيات'],
                        ['templates.manage', 'admin.templates', '📝 نماذج الخطابات'],
                        ['settings.manage', 'admin.settings', '⚙️ إعدادات الجامعة'],
                    ] as [$perm, $route, $label])
                        @can($perm)
                            <a href="{{ route($route) }}" wire:navigate @class(['block rounded px-2 py-1 text-xs hover:bg-white/15', 'bg-white/15 text-white' => request()->routeIs($route)])>{{ $label }}</a>
                        @endcan
                    @endforeach
                </div>
            @endcan

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="text-xs text-brand-100/80 hover:text-white">تسجيل الخروج ←</button>
            </form>
        </div>
    </aside>

    {{-- المحتوى --}}
    <main class="min-w-0 flex-1">
        {{-- شريط علوي للجوال --}}
        <div class="flex items-center justify-between bg-brand-800 px-4 py-3 text-white md:hidden">
            <span class="font-bold">{{ \App\Models\Setting::get('university_name_ar') }}</span>
            <a href="{{ route('letters.create') }}" wire:navigate class="text-sm">✉️ جديد</a>
        </div>
        <div class="flex gap-2 overflow-x-auto bg-white px-3 py-2 text-sm md:hidden">
            @foreach (\App\Services\Mailbox::BOXES as $key => $label)
                <a href="{{ route('mail.box', $key) }}" wire:navigate class="shrink-0 rounded-full border px-3 py-1">{{ $label }}</a>
            @endforeach
        </div>

        <div class="mx-auto max-w-6xl p-4 md:p-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            {{ $slot }}
        </div>
    </main>
</div>
</body>
</html>
