<?php

namespace App\Livewire\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('المستخدمون')]
class Users extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $filter = '';

    public ?int $userId = null;

    public bool $isActive = true;

    public bool $isAdmin = false;

    /** @var array<int> */
    public array $roleIds = [];

    public string $newPassword = '';

    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function allRoles()
    {
        return Role::orderBy('label')->get();
    }

    #[Computed]
    public function selected(): ?User
    {
        return $this->userId ? User::with(['homeUnit', 'roles', 'activeAssignments.unit'])->find($this->userId) : null;
    }

    public function select(int $id): void
    {
        $u = User::with('roles')->findOrFail($id);
        $this->userId = $u->id;
        $this->isActive = $u->is_active;
        $this->isAdmin = $u->is_admin;
        $this->roleIds = $u->roles->modelKeys();
        $this->newPassword = '';
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('users.manage');
        $user = User::findOrFail($this->userId);
        $me = auth()->user();

        $this->validate([
            'roleIds' => 'array',
            'roleIds.*' => 'integer|exists:roles,id',
            'newPassword' => 'nullable|string|min:8|max:100',
        ], [], ['newPassword' => 'كلمة المرور']);

        if ($user->is($me) && (! $this->isActive || ($me->is_admin && ! $this->isAdmin))) {
            $this->addError('isActive', 'لا يمكنك تعطيل حسابك أو سحب صلاحية مدير النظام من نفسك.');

            return;
        }

        $user->is_active = $this->isActive;
        if ($me->is_admin) { // منح «مدير النظام» حصريًا لمدير نظام
            $user->is_admin = $this->isAdmin;
        }
        if ($this->newPassword !== '') {
            $user->password = Hash::make($this->newPassword);
        }
        $user->save();

        $user->roles()->syncWithPivotValues($this->roleIds, ['granted_by' => $me->id]);
        $user->forgetPermissions();

        $this->newPassword = '';
        unset($this->selected);
        session()->flash('status', 'تم حفظ بيانات '.$user->name.'.');
    }

    public function render()
    {
        $users = User::query()
            ->with(['homeUnit', 'roles', 'activeAssignments.unit'])
            ->when(trim($this->search), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%$s%")->orWhere('employee_no', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->when($this->filter === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($this->filter === 'admins', fn ($q) => $q->where(fn ($w) => $w->where('is_admin', true)->orHas('roles')))
            ->when($this->filter === 'unit_users', fn ($q) => $q->whereHas('assignments', fn ($a) => $a->active()))
            ->when(str_starts_with($this->filter, 'role:'), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey((int) substr($this->filter, 5))))
            ->orderBy('name')
            ->paginate(25);

        return view('livewire.admin.users', ['users' => $users]);
    }
}
