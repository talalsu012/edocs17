<?php

namespace App\Livewire\Admin;

use App\Models\Letter;
use App\Models\Setting;
use App\Models\StaffAllowedTarget;
use App\Models\Unit;
use App\Services\TrainingData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('إعدادات الجامعة')]
class Settings extends Component
{
    use WithFileUploads;

    public string $university_name_ar = '';

    public string $university_name_en = '';

    public string $system_name = '';

    public string $number_prefix = '';

    public string $letterhead_address = '';

    public string $letterhead_phone = '';

    public string $letterhead_email = '';

    public string $letterhead_website = '';

    public bool $show_hijri = true;

    public int $reminder_days_before = 2;

    public int $max_attachment_mb = 10;

    public string $allowed_extensions = '';

    /** @var array<int> */
    public array $staffTargets = [];

    public $logo = null;

    public string $unitFilter = '';

    protected const TEXT_KEYS = [
        'university_name_ar', 'university_name_en', 'system_name', 'number_prefix',
        'letterhead_address', 'letterhead_phone', 'letterhead_email', 'letterhead_website',
    ];

    public function mount(): void
    {
        $this->authorize('settings.manage');

        foreach (self::TEXT_KEYS as $k) {
            $this->{$k} = (string) Setting::get($k, '');
        }
        $this->show_hijri = Setting::get('show_hijri', true);
        $this->reminder_days_before = Setting::get('reminder_days_before', 2);
        $this->max_attachment_mb = Setting::get('max_attachment_mb', 10);
        $this->allowed_extensions = implode(', ', Setting::get('allowed_extensions', []));
        $this->staffTargets = StaffAllowedTarget::pluck('unit_id')->all();
    }

    #[Computed]
    public function units()
    {
        return Unit::active()
            ->when(trim($this->unitFilter), fn ($q, $f) => $q->where('name', 'like', "%$f%"))
            ->orderBy('path')->get(['id', 'name', 'depth']);
    }

    #[Computed]
    public function logoUrl(): ?string
    {
        $p = Setting::get('logo_path');

        return $p && file_exists(public_path($p)) ? asset($p) : null;
    }

    public function save(): void
    {
        $this->authorize('settings.manage');

        $this->validate([
            'university_name_ar' => 'required|string|max:150',
            'university_name_en' => 'nullable|string|max:150',
            'system_name' => 'required|string|max:150',
            'number_prefix' => 'required|string|max:10',
            'letterhead_address' => 'nullable|string|max:255',
            'letterhead_phone' => 'nullable|string|max:50',
            'letterhead_email' => 'nullable|email|max:150',
            'letterhead_website' => 'nullable|string|max:150',
            'reminder_days_before' => 'required|integer|min:0|max:30',
            'max_attachment_mb' => 'required|integer|min:1|max:100',
            'allowed_extensions' => ['required', 'string', 'regex:/^[a-z0-9,\s]+$/i'],
            'staffTargets' => 'array',
            'staffTargets.*' => 'integer|exists:units,id',
            'logo' => 'nullable|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ], [], [
            'university_name_ar' => 'اسم الجامعة', 'system_name' => 'اسم النظام', 'number_prefix' => 'بادئة الترقيم',
            'letterhead_email' => 'البريد', 'allowed_extensions' => 'أنواع الملفات', 'logo' => 'الشعار',
        ]);

        $values = [];
        foreach (self::TEXT_KEYS as $k) {
            $values[$k] = trim($this->{$k});
        }
        $values['show_hijri'] = $this->show_hijri;
        $values['reminder_days_before'] = $this->reminder_days_before;
        $values['max_attachment_mb'] = $this->max_attachment_mb;
        $values['allowed_extensions'] = array_values(array_unique(array_filter(
            array_map(fn ($e) => strtolower(trim($e)), explode(',', $this->allowed_extensions))
        )));

        if ($this->logo) {
            File::ensureDirectoryExists(public_path('uploads'));
            $name = 'uploads/logo-'.Str::random(8).'.'.$this->logo->getClientOriginalExtension();
            File::copy($this->logo->getRealPath(), public_path($name));
            if (($old = Setting::get('logo_path')) && str_starts_with($old, 'uploads/logo-')) {
                File::delete(public_path($old));
            }
            $values['logo_path'] = $name;
            $this->logo = null;
        }

        Setting::put($values, auth()->user());

        // الجهات المسموح للموظفين مراسلتها
        StaffAllowedTarget::whereNotIn('unit_id', $this->staffTargets)->delete();
        foreach ($this->staffTargets as $id) {
            StaffAllowedTarget::firstOrCreate(['unit_id' => $id]);
        }

        unset($this->logoUrl);
        session()->flash('status', 'تم حفظ إعدادات الجامعة.');
    }

    public function removeLogo(): void
    {
        $this->authorize('settings.manage');
        if (($old = Setting::get('logo_path')) && str_starts_with($old, 'uploads/logo-')) {
            File::delete(public_path($old));
        }
        Setting::put(['logo_path' => null], auth()->user());
        unset($this->logoUrl);
    }

    #[Computed]
    public function trainingCount(): int
    {
        return Letter::where('is_training', true)->count();
    }

    public function generateTraining(TrainingData $training): void
    {
        $this->authorize('training.manage');
        try {
            $s = $training->generate();
            session()->flash('status', "تم إنشاء بيانات التدريب: {$s['letters']} خطابًا و{$s['attachments']} مرفقًا.");
        } catch (\RuntimeException $e) {
            $this->addError('training', $e->getMessage());
        }
        unset($this->trainingCount);
    }

    public function purgeTraining(TrainingData $training): void
    {
        $this->authorize('training.manage');
        $n = $training->purge();
        session()->flash('status', "تم حذف {$n} خطابًا تدريبيًا.");
        unset($this->trainingCount);
    }

    public function render()
    {
        return view('livewire.admin.settings');
    }
}
