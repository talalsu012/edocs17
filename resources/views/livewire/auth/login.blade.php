<div class="flex min-h-screen items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <div class="mb-6 text-center">
            <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-700 text-2xl text-white">✉️</div>
            <h1 class="text-xl font-bold text-slate-800">المراسلات الإلكترونية</h1>
            <p class="text-sm text-slate-500">{{ \App\Models\Setting::get('university_name_ar') }}</p>
        </div>

        <form wire:submit="authenticate" class="card space-y-4 p-6">
            <div>
                <label class="label" for="login">البريد الإلكتروني أو الرقم الوظيفي</label>
                <input id="login" type="text" wire:model="login" class="input" autofocus autocomplete="username" dir="auto">
                @error('login') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password">كلمة المرور</label>
                <input id="password" type="password" wire:model="password" class="input" autocomplete="current-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" wire:model="remember" class="rounded border-slate-300"> تذكرني
            </label>
            <button type="submit" class="btn-primary w-full" wire:loading.attr="disabled">
                <span wire:loading.remove>دخول</span>
                <span wire:loading>جارٍ التحقق…</span>
            </button>
        </form>
    </div>
</div>
