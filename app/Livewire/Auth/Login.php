<?php

namespace App\Livewire\Auth;

use App\Services\MangDirectory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('تسجيل الدخول')]
class Login extends Component
{
    #[Validate('required|string', as: 'البريد أو الرقم الوظيفي')]
    public string $login = '';

    #[Validate('required|string', as: 'كلمة المرور')]
    public string $password = '';

    public bool $remember = false;

    public function authenticate(MangDirectory $mang)
    {
        $this->validate();

        $key = Str::lower($this->login).'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'login' => 'محاولات كثيرة. حاول بعد '.RateLimiter::availableIn($key).' ثانية.',
            ]);
        }

        $field = str_contains($this->login, '@') ? 'email' : 'employee_no';

        if (! Auth::attempt([$field => $this->login, 'password' => $this->password, 'is_active' => true], $this->remember)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['login' => 'بيانات الدخول غير صحيحة.']);
        }

        RateLimiter::clear($key);
        session()->regenerate();

        // تحديث الاسم والمسمى وجهة العمل من قاعدة الموظفين mang
        try {
            $mang->syncUser(Auth::user());
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->redirectIntended(route('mail.box', 'inbox'), navigate: true);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
