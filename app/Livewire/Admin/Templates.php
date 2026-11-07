<?php

namespace App\Livewire\Admin;

use App\Enums\TemplateAudience;
use App\Models\Template;
use App\Models\Unit;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('نماذج الخطابات')]
class Templates extends Component
{
    /** الحقول التي تُملأ تلقائيًا عند اختيار النموذج */
    public const AUTO = [
        'جهة_المرسل' => 'unit.name', 'رقم_الصادر' => 'letter.outgoing_no', 'التاريخ' => 'today',
        'اسم_الموظف' => 'user.name', 'الرقم_الوظيفي' => 'user.employee_no',
    ];

    public const TYPES = ['text' => 'نص قصير', 'textarea' => 'نص طويل', 'number' => 'رقم', 'date' => 'تاريخ', 'time' => 'وقت', 'select' => 'قائمة اختيار'];

    public ?int $templateId = null;

    public bool $editing = false;

    public string $name = '';

    public string $category = '';

    public string $subject_pattern = '';

    public string $body = '';

    public string $allowed_for = 'both';

    public ?int $default_recipient_unit_id = null;

    /** @var list<array{name:string, label:string, type:string, required:bool, options:string}> */
    public array $fields = [];

    public function mount(): void
    {
        $this->authorize('templates.manage');
    }

    #[Computed]
    public function templates()
    {
        $latest = Template::query()->selectRaw('max(id)')->groupBy('code');

        return Template::whereIn('id', $latest)->withCount('letters')->orderBy('category')->orderBy('name')->get();
    }

    #[Computed]
    public function units()
    {
        return Unit::active()->orderBy('path')->get(['id', 'name', 'depth']);
    }

    public function create(): void
    {
        $this->reset(['templateId', 'name', 'category', 'subject_pattern', 'body', 'default_recipient_unit_id', 'fields']);
        $this->allowed_for = 'both';
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $t = Template::findOrFail($id);
        $this->templateId = $t->id;
        $this->name = $t->name;
        $this->category = (string) $t->category;
        $this->subject_pattern = (string) $t->subject_pattern;
        $this->body = $t->body_with_fields;
        $this->allowed_for = $t->allowed_for->value;
        $this->default_recipient_unit_id = $t->default_recipient_unit_id;
        $this->fields = collect($t->fields_json ?? [])
            ->reject(fn ($f) => isset($f['auto']))
            ->map(fn ($f) => [
                'name' => $f['name'], 'label' => $f['label'] ?? $f['name'], 'type' => $f['type'] ?? 'text',
                'required' => (bool) ($f['required'] ?? false), 'options' => implode('، ', $f['options'] ?? []),
            ])->values()->all();
        $this->syncFields();
        $this->resetErrorBag();
        $this->editing = true;
    }

    /** تحديث قائمة الحقول من النص: كل {{حقل}} جديد يُضاف، والمحذوف من النص يُزال. */
    public function syncFields(): void
    {
        preg_match_all('/\{\{\s*([^}\s]+)\s*\}\}/u', $this->subject_pattern.' '.$this->body, $m);
        $names = array_values(array_diff(array_unique($m[1]), array_keys(self::AUTO)));

        $existing = collect($this->fields)->keyBy('name');
        $this->fields = array_map(fn ($n) => $existing[$n]
            ?? ['name' => $n, 'label' => str_replace('_', ' ', $n), 'type' => 'text', 'required' => true, 'options' => ''], $names);
    }

    public function updatedBody(): void
    {
        $this->syncFields();
    }

    public function updatedSubjectPattern(): void
    {
        $this->syncFields();
    }

    public function save(): void
    {
        $this->authorize('templates.manage');
        $this->syncFields();

        $this->validate([
            'name' => 'required|string|max:150',
            'category' => 'nullable|string|max:50',
            'subject_pattern' => 'nullable|string|max:255',
            'body' => 'required|string|max:20000',
            'allowed_for' => ['required', Rule::enum(TemplateAudience::class)],
            'default_recipient_unit_id' => 'nullable|integer|exists:units,id',
            'fields.*.label' => 'required|string|max:100',
            'fields.*.type' => ['required', Rule::in(array_keys(self::TYPES))],
        ], [], ['name' => 'اسم النموذج', 'body' => 'نص النموذج', 'fields.*.label' => 'اسم الحقل']);

        preg_match_all('/\{\{\s*([^}\s]+)\s*\}\}/u', $this->subject_pattern.' '.$this->body, $m);
        $autos = array_values(array_intersect(array_keys(self::AUTO), array_unique($m[1])));

        $fieldsJson = array_merge(
            array_map(fn ($n) => ['name' => $n, 'label' => str_replace('_', ' ', $n), 'type' => 'text', 'auto' => self::AUTO[$n]], $autos),
            array_map(fn ($f) => array_filter([
                'name' => $f['name'], 'label' => $f['label'], 'type' => $f['type'], 'required' => (bool) $f['required'],
                'options' => $f['type'] === 'select' ? array_values(array_filter(array_map('trim', preg_split('/[،,]/u', $f['options'])))) : null,
            ], fn ($v) => $v !== null), $this->fields),
        );

        $data = [
            'name' => $this->name,
            'category' => $this->category ?: null,
            'subject_pattern' => $this->subject_pattern ?: null,
            'body_with_fields' => $this->body,
            'fields_json' => $fieldsJson,
            'allowed_for' => $this->allowed_for,
            'default_recipient_unit_id' => $this->default_recipient_unit_id,
        ];

        if ($this->templateId) {
            $t = Template::findOrFail($this->templateId);
            // نموذج استُخدم في خطابات: إصدار جديد حتى لا يتغير سجل القديم
            $t = $t->letters()->exists() ? $t->newVersion($data) : tap($t)->update($data);
            $msg = 'تم حفظ النموذج (الإصدار '.$t->version.').';
        } else {
            $code = Str::slug($this->name, '_') ?: 'tpl';
            $code = Template::where('code', $code)->exists() ? $code.'_'.Str::lower(Str::random(4)) : $code;
            $t = Template::create($data + ['code' => $code, 'version' => 1, 'is_active' => true, 'created_by' => auth()->id()]);
            $msg = 'تم إنشاء النموذج.';
        }

        $this->templateId = $t->id;
        unset($this->templates);
        session()->flash('status', $msg);
    }

    public function toggle(int $id): void
    {
        $this->authorize('templates.manage');
        $t = Template::findOrFail($id);
        $t->update(['is_active' => ! $t->is_active]);
        unset($this->templates);
    }

    public function render()
    {
        return view('livewire.admin.templates', [
            'preview' => $this->editing ? preg_replace_callback('/\{\{\s*([^}\s]+)\s*\}\}/u',
                fn ($m) => '['.(collect($this->fields)->firstWhere('name', $m[1])['label'] ?? str_replace('_', ' ', $m[1])).']', $this->body) : null,
        ]);
    }
}
