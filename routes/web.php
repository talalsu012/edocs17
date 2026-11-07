<?php

use App\Http\Controllers\PrintController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SessionController;
use App\Livewire\Admin\Assignments;
use App\Livewire\Admin\Roles;
use App\Livewire\Admin\Settings;
use App\Livewire\Admin\Templates;
use App\Livewire\Admin\Users;
use App\Livewire\Auth\Login;
use App\Livewire\Compose;
use App\Livewire\Dashboard;
use App\Livewire\Mailbox;
use App\Livewire\ShowLetter;
use Illuminate\Support\Facades\Route;

// صفحة التحقق من الخطاب المطبوع (عامة — يفتحها رمز QR)
Route::get('/verify/{letter}/{code}', [PrintController::class, 'verify'])
    ->middleware('throttle:30,1')->name('letters.verify');

Route::middleware('guest')->group(function () {
    Route::livewire('/login', Login::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::redirect('/', '/mail/inbox');

    Route::livewire('/mail/{box}', Mailbox::class)
        ->whereIn('box', array_keys(App\Services\Mailbox::BOXES))
        ->name('mail.box');

    Route::livewire('/letters/new', Compose::class)->name('letters.create');
    Route::livewire('/letters/{letter}/edit', Compose::class)->name('letters.edit');
    Route::livewire('/letters/{letter}', ShowLetter::class)->name('letters.show');
    Route::get('/letters/{letter}/print', [PrintController::class, 'letter'])->name('letters.print');

    // الإدارة — كل شاشة بصلاحيتها
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::livewire('/assignments', Assignments::class)->middleware('can:assignments.manage')->name('assignments');
        Route::get('/units/{unit}/handover', [PrintController::class, 'handover'])->middleware('can:assignments.manage')->name('handover');
        Route::livewire('/settings', Settings::class)->middleware('can:settings.manage')->name('settings');
        Route::livewire('/roles', Roles::class)->middleware('can:roles.manage')->name('roles');
        Route::livewire('/users', Users::class)->middleware('can:users.manage')->name('users');
    });

    Route::livewire('/dashboard', Dashboard::class)->name('dashboard');
    Route::get('/reports/register', [ReportController::class, 'register'])->name('reports.register');
    Route::livewire('/admin/templates', Templates::class)->middleware('can:templates.manage')->name('admin.templates');

    Route::get('/attachments/{attachment}', [SessionController::class, 'attachment'])->name('attachments.download');
    Route::get('/attachments/{attachment}/view', [SessionController::class, 'viewAttachment'])->name('attachments.view');
    Route::post('/active-unit', [SessionController::class, 'switchUnit'])->name('active-unit');
    Route::post('/logout', [SessionController::class, 'logout'])->name('logout');
});
