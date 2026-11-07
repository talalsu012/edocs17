<?php

namespace App\Livewire;

use App\Enums\BroadcastScope;
use App\Enums\Priority;
use App\Enums\UnitType;
use App\Models\Letter;
use App\Models\Setting;
use App\Models\StaffAllowedTarget;
use App\Models\Template;
use App\Models\Unit;
use App\Services\BroadcastService;
use App\Services\LetterService;
use App\Support\ActiveUnit;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Compose extends Component
{
    use WithFileUploads;

    public ?int $letterId = null;

    public ?int $parentLetterId = null;

    /** unit: باسم الجهة — personal: طلب شخصي — broadcast: تعميم */
    public string $mode = 'unit';

    public string $broadcastScope = 'subtree';

    public ?int $broadcastRoot = null;

    public ?string $broadcastType = null;

    public ?int $unitId = null;

    public string $subject = '';

    public string $body = '';

    public string $priority = 'normal';

    public bool $isConfidential = false;

    public ?string $dueDate = null;

    /** @var array<int> */
    public array $to = [];

    /** @var array<int> */
    public array $cc = [];

    public ?int $templateId = null;

    public array $files = [];

    public string $unitFilter = '';

    public function mount(?Letter $letter = null): void
    {
        $user = auth()->user();
        $this->unitId = ActiveUnit::get()?->id;
        $this->mode = $this->unitId ? 'unit' : 'personal';

        if ($letter?->exists) {
            abort_unless(app(LetterService::class)->canEditDraft($user, $letter), 403);
            $this->letterId = $letter->id;
            $this->mode = $letter->isBroadcast() ? 'broadcast' : ($letter->sender_unit_id ? 'unit' : 'personal');
            $this->unitId = $letter->sender_unit_id ?? $this->unitId;
            if ($t = $letter->broadcastTargets()->first()) {
                $this->broadcastScope = $t->scope->value;
                $this->broadcastRoot = $t->root_unit_id;
                $this->broadcastType = $t->unit_type?->value;
            }
            $this->fill([
                'subject' => $letter->subject,
                'body' => (string) $letter->body,
                'priority' => $letter->priority->value,
                'isConfidential' => $letter->is_confidential,
                'dueDate' => $letter->due_date?->toDateString(),
                'templateId' => $letter->template_id,
                'parentLetterId' => $letter->parent_letter_id,
                'to' => $letter->toRecipients()->pluck('unit_id')->all(),
                'cc' => $letter->ccRecipients()->pluck('unit_id')->all(),
            ]);

            return;
        }

        if (request()->query('mode') === 'broadcast' && $this->unitId) {
            $this->mode = 'broadcast';
            $this->broadcastRoot = $this->unitId;
        }

        // رد على خطاب: المستلم = الجهة الأصلية
        if ($replyTo = request()->integer('reply_to')) {
            $parent = Letter::findOrFail($replyTo);
            abort_unless($parent->isVisibleTo($user), 403);
            $this->parentLetterId = $parent->id;
            $this->subject = 'رد: '.$parent->subject;
            $this->to = array_filter([$parent->sender_unit_id ?? $parent->origin_unit_id]);
            $this->body = 'بالإشارة إلى خطابكم رقم '.$parent->outgoing_no.' بتاريخ '.$parent->sent_at?->format('Y/m/d')."، نفيدكم بالآتي:\n\n";
        }
    }

    #[Computed]
    public function myUnits()
    {
        return auth()->user()->currentUnits();
    }

    /** الجهات المتاحة كمستلمين، مرتبة كشجرة. */
    #[Computed]
    public function recipientOptions()
    {
        $q = Unit::active()->orderBy('path');

        if ($this->mode === 'personal') {
            $q->whereIn('id', StaffAllowedTarget::pluck('unit_id'));
        } elseif ($this->unitId) {
            $q->whereKeyNot($this->unitId);
        }

        if ($f = trim($this->unitFilter)) {
            $q->where(fn ($w) => $w->where('name', 'like', "%$f%")->orWhere('code', 'like', "%$f%"));
        }

        return $q->get(['id', 'name', 'code', 'depth', 'path', 'type']);
    }

    /** نطاقات التعميم المتاحة للجهة المرسلة. */
    #[Computed]
    public function broadcastScopes(): array
    {
        $unit = Unit::find($this->unitId);

        return $unit ? app(BroadcastService::class)->allowedScopes($unit) : [];
    }

    /** الفروع التي يمكن التعميم عليها: فرع الجهة نفسها، أو كل الشجرة للجهات المخوّلة. */
    #[Computed]
    public function broadcastRoots()
    {
        $unit = Unit::find($this->unitId);
        if (! $unit) {
            return collect();
        }

        return ($unit->can_broadcast_all ? Unit::active() : $unit->subtree()->where('is_active', true))
            ->orderBy('path')->get(['id', 'name', 'depth']);
    }

    #[Computed]
    public function templates()
    {
        $q = Template::latestActive()->orderBy('category')->orderBy('name');
        if ($this->mode === 'broadcast') {
            return $q->forUnits()->where('code', 'circular')->get();
        }

        return $this->mode === 'personal' ? $q->forStaff()->get() : $q->forUnits()->get();
    }

    #[Computed]
    public function existingAttachments()
    {
        return $this->letterId ? Letter::find($this->letterId)?->attachments ?? collect() : collect();
    }

    public function updatedMode(): void
    {
        $this->to = $this->cc = [];
        $this->templateId = null;
        if ($this->mode !== 'unit') {
            $this->isConfidential = false;
        }
        if ($this->mode === 'broadcast') {
            $this->broadcastRoot ??= $this->unitId;
        }
    }

    /** اختيار نموذج: تعبئة الموضوع والنص بالحقول المعروفة تلقائيًا. */
    public function updatedTemplateId(): void
    {
        $t = $this->templateId ? Template::find($this->templateId) : null;
        if (! $t) {
            return;
        }

        $user = auth()->user();
        $unit = $this->mode === 'unit' ? Unit::find($this->unitId) : $user->homeUnit;
        $values = [
            'جهة_المرسل' => $unit?->name,
            'رقم_الصادر' => '(يُعطى عند الإرسال)',
            'التاريخ' => now()->format('Y/m/d'),
            'اسم_الموظف' => $user->displayName(),
            'الرقم_الوظيفي' => $user->employee_no,
        ];
        $keep = fn (string $text) => preg_replace_callback('/\{\{\s*([^}\s]+)\s*\}\}/u',
            fn ($m) => $values[$m[1]] ?? '['.str_replace('_', ' ', $m[1]).']', $text);

        $this->subject = $keep($t->subject_pattern ?? $t->name);
        $this->body = $keep($t->body_with_fields);
        if ($t->default_recipient_unit_id && $this->recipientOptions->contains('id', $t->default_recipient_unit_id)) {
            $this->to = [$t->default_recipient_unit_id];
        }
    }

    protected function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['unit', 'personal', 'broadcast'])],
            'unitId' => [Rule::requiredIf($this->mode !== 'personal'), 'nullable', 'integer'],
            'subject' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:50000'],
            'priority' => ['required', Rule::enum(Priority::class)],
            'isConfidential' => ['boolean'],
            'dueDate' => ['nullable', 'date', 'after_or_equal:today'],
            'to' => [Rule::requiredIf($this->mode !== 'broadcast'), 'array'],
            'broadcastScope' => [Rule::requiredIf($this->mode === 'broadcast'), Rule::enum(BroadcastScope::class)],
            'broadcastRoot' => [Rule::requiredIf($this->mode === 'broadcast' && $this->broadcastScope === 'subtree'), 'nullable', 'integer'],
            'broadcastType' => [Rule::requiredIf($this->mode === 'broadcast' && $this->broadcastScope === 'unit_type'), 'nullable', Rule::enum(UnitType::class)],
            'to.*' => ['integer', 'exists:units,id'],
            'cc' => ['array'],
            'cc.*' => ['integer', 'exists:units,id'],
            'files.*' => ['file', 'max:'.(Setting::get('max_attachment_mb', 10) * 1024),
                'mimes:'.implode(',', Setting::get('allowed_extensions', ['pdf']))],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'subject' => 'الموضوع', 'body' => 'النص', 'to' => 'المستلمون', 'cc' => 'النسخ',
            'dueDate' => 'موعد الرد', 'files.*' => 'المرفق', 'unitId' => 'الجهة المرسلة',
            'broadcastRoot' => 'الفرع المستهدف', 'broadcastType' => 'نوع الجهات',
        ];
    }

    protected function persist(LetterService $service): Letter
    {
        $this->validate();

        $letter = $service->saveDraft(auth()->user(), [
            'mode' => $this->mode,
            'unit_id' => $this->unitId,
            'subject' => $this->subject,
            'body' => $this->body,
            'priority' => $this->priority,
            'is_confidential' => $this->isConfidential,
            'due_date' => $this->dueDate,
            'to' => $this->to,
            'cc' => $this->mode === 'unit' ? $this->cc : [],
            'broadcast' => [
                'scope' => $this->broadcastScope,
                'root_unit_id' => $this->broadcastRoot,
                'unit_type' => $this->broadcastType,
            ],
            'template_id' => $this->templateId,
            'parent_letter_id' => $this->parentLetterId,
        ], $this->letterId ? Letter::find($this->letterId) : null, $this->files);

        $this->letterId = $letter->id;
        $this->files = [];

        return $letter;
    }

    public function saveDraft(LetterService $service)
    {
        $this->persist($service);
        session()->flash('status', 'تم حفظ المسودة.');

        return $this->redirectRoute('letters.edit', $this->letterId, navigate: true);
    }

    public function send(LetterService $service)
    {
        $letter = $service->send(auth()->user(), $this->persist($service));
        session()->flash('status', 'تم إرسال الخطاب برقم '.$letter->outgoing_no);

        return $this->redirectRoute('letters.show', $letter, navigate: true);
    }

    public function discard(LetterService $service)
    {
        if ($this->letterId) {
            $service->discardDraft(auth()->user(), Letter::findOrFail($this->letterId));
        }

        return $this->redirectRoute('mail.box', 'drafts', navigate: true);
    }

    public function render()
    {
        return view('livewire.compose')->title($this->letterId ? 'تعديل مسودة' : ($this->mode === 'broadcast' ? 'تعميم جديد' : 'خطاب جديد'));
    }
}
