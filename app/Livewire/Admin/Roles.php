<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('الأدوار والصلاحيات')]
class Roles extends Component
{
    public ?int $roleId = null;

    public bool $editing = false;

    public string $label = '';

    public string $name = '';

    public string $description = '';

    /** @var array<string> */
    public array $permissions = [];

    public function mount(): void
    {
        $this->authorize('roles.manage');
    }

    #[Computed]
    public function roles()
    {
        return Role::withCount('users')->with('permissionRows')->orderByDesc('is_system')->orderBy('label')->get();
    }

    public function create(): void
    {
        $this->reset(['roleId', 'label', 'name', 'description', 'permissions']);
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function edit(int $id): void
    {
        $role = Role::with('permissionRows')->findOrFail($id);
        $this->roleId = $role->id;
        $this->label = $role->label;
        $this->name = $role->name;
        $this->description = (string) $role->description;
        $this->permissions = $role->permissions();
        $this->resetErrorBag();
        $this->editing = true;
    }

    public function save(): void
    {
        $this->authorize('roles.manage');
        if (! $this->roleId && $this->name === '') {
            $this->name = Str::slug($this->label, '_') ?: 'role_'.Str::lower(Str::random(6));
        }

        $this->validate([
            'label' => 'required|string|max:100',
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_\-]+$/', Rule::unique('roles', 'name')->ignore($this->roleId)],
            'description' => 'nullable|string|max:500',
            'permissions' => 'array',
            'permissions.*' => [Rule::enum(Permission::class)],
        ], [], ['label' => 'اسم الدور', 'name' => 'المعرّف']);

        $role = $this->roleId ? Role::findOrFail($this->roleId) : new Role(['is_system' => false]);
        $role->fill(['label' => $this->label, 'description' => $this->description ?: null]);
        if (! $role->is_system) {
            $role->name = $this->name;
        }
        $role->save();
        $role->syncPermissions($this->permissions);

        $this->editing = false;
        unset($this->roles);
        session()->flash('status', 'تم حفظ الدور «'.$role->label.'».');
    }

    public function delete(int $id): void
    {
        $this->authorize('roles.manage');
        $role = Role::findOrFail($id);
        abort_if($role->is_system, 403, 'أدوار النظام لا تُحذف.');
        $role->delete();
        $this->editing = false;
        unset($this->roles);
        session()->flash('status', 'تم حذف الدور.');
    }

    public function render()
    {
        return view('livewire.admin.roles', [
            'groups' => collect(Permission::cases())->groupBy(fn (Permission $p) => $p->group()),
        ]);
    }
}
