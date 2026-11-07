<?php

namespace App\Livewire\Admin;

use App\Models\Unit;
use App\Models\UnitAssignment;
use App\Models\User;
use App\Services\AssignmentService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('التكليفات والتنقلات')]
class Assignments extends Component
{
    #[Url(as: 'q', except: '')]
    public string $unitSearch = '';

    #[Url(as: 'vacant', except: false)]
    public bool $onlyVacant = false;

    #[Url(as: 'unit')]
    public ?int $unitId = null;

    public string $userSearch = '';

    public ?int $userId = null;

    public string $kind = 'original';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public string $decisionNo = '';

    public ?string $decisionDate = null;

    public bool $endOthers = true;

    public function mount(): void
    {
        $this->authorize('assignments.manage');
        $this->startDate = today()->toDateString();
    }

    #[Computed]
    public function units()
    {
        return Unit::query()
            ->with('activeAssignment.user')
            ->when(trim($this->unitSearch), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%$s%")->orWhere('code', 'like', "%$s%")))
            ->when($this->onlyVacant, fn ($q) => $q->where('is_active', true)->whereDoesntHave('assignments', fn ($a) => $a->active()))
            ->orderBy('path')
            ->get();
    }

    #[Computed]
    public function unit(): ?Unit
    {
        return $this->unitId ? Unit::with(['activeAssignment.user', 'parent'])->find($this->unitId) : null;
    }

    #[Computed]
    public function history()
    {
        return $this->unitId
            ? UnitAssignment::with('user', 'assigner')->where('unit_id', $this->unitId)->orderByDesc('start_date')->orderByDesc('id')->get()
            : collect();
    }

    #[Computed]
    public function handover(): ?array
    {
        return $this->unit ? app(AssignmentService::class)->handover($this->unit) : null;
    }

    #[Computed]
    public function userResults()
    {
        $s = trim($this->userSearch);
        if (mb_strlen($s) < 2) {
            return collect();
        }

        return User::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', "%$s%")->orWhere('employee_no', 'like', "%$s%")->orWhere('email', 'like', "%$s%"))
            ->with('homeUnit')->orderBy('name')->limit(10)->get();
    }

    #[Computed]
    public function vacantCount(): int
    {
        return Unit::active()->whereDoesntHave('assignments', fn ($a) => $a->active())->count();
    }

    public function selectUnit(int $id): void
    {
        $this->unitId = $id;
        $this->resetForm();
    }

    public function pickUser(int $id): void
    {
        $this->userId = $id;
        $this->userSearch = User::find($id)?->name ?? '';
    }

    public function assign(AssignmentService $service): void
    {
        $this->authorize('assignments.manage');
        $this->validate([
            'unitId' => ['required', 'exists:units,id'],
            'userId' => ['required', 'exists:users,id'],
            'kind' => ['required', Rule::in(['original', 'acting'])],
            'startDate' => ['required', 'date'],
            'endDate' => [Rule::requiredIf($this->kind === 'acting'), 'nullable', 'date', 'after_or_equal:startDate'],
            'decisionNo' => ['nullable', 'string', 'max:100'],
            'decisionDate' => ['nullable', 'date'],
        ], [], [
            'userId' => 'المستخدم', 'startDate' => 'تاريخ البداية', 'endDate' => 'تاريخ النهاية',
            'decisionNo' => 'رقم القرار', 'decisionDate' => 'تاريخ القرار',
        ]);

        $user = User::findOrFail($this->userId);
        $service->assign(auth()->user(), $this->unit, $user, [
            'kind' => $this->kind,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'decision_no' => $this->decisionNo ?: null,
            'decision_date' => $this->decisionDate,
            'end_other_assignments' => $this->endOthers,
        ]);

        session()->flash('status', 'تم تكليف '.$user->name.' بـ'.$this->unit->name.'.');
        $this->resetForm();
        unset($this->units, $this->unit, $this->history, $this->vacantCount);
    }

    public function endAssignment(int $id, AssignmentService $service): void
    {
        $this->authorize('assignments.manage');
        $a = UnitAssignment::where('unit_id', $this->unitId)->findOrFail($id);
        $service->end($a);
        session()->flash('status', 'تم إنهاء تكليف '.$a->user->name.'.');
        unset($this->units, $this->unit, $this->history, $this->vacantCount);
    }

    protected function resetForm(): void
    {
        $this->reset(['userSearch', 'userId', 'kind', 'endDate', 'decisionNo', 'decisionDate']);
        $this->endOthers = true;
        $this->startDate = today()->toDateString();
        $this->resetErrorBag();
    }

    public function render()
    {
        return view('livewire.admin.assignments');
    }
}
