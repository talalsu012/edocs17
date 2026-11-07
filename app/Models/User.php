<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\Permission;
use App\Models\Mang\Employee;
use App\Models\Mang\ReadOnlyMangModel;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'employee_no', 'email', 'phone', 'job_title', 'home_unit_id', 'password', 'is_admin', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** ذاكرة مؤقتة للجهات الحالية خلال الطلب الواحد */
    protected ?Collection $currentUnitsCache = null;

    /** @var list<string>|null */
    protected ?array $permissionsCache = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /* ---------- العلاقات ---------- */

    /** جهة عمل الموظف (كموظف، لا كمستخدم جهة). */
    public function homeUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'home_unit_id');
    }

    /** سجل الموظف في قاعدة الموظفين mang (قراءة مباشرة عبر الرقم الوظيفي). */
    public function mangEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_no', 'employee_id');
    }

    /** الاسم من mang مباشرة، وإلا الاسم المخزّن. */
    public function displayName(): string
    {
        return $this->fromMang(fn ($e) => $e->displayName()) ?: $this->name;
    }

    /** المسمى الوظيفي من mang مباشرة، وإلا المخزّن. */
    public function displayJobTitle(): ?string
    {
        return $this->fromMang(fn ($e) => $e->jobTitle()) ?: $this->job_title;
    }

    protected function fromMang(callable $read): mixed
    {
        if (! $this->employee_no || ! ReadOnlyMangModel::available()) {
            return null;
        }

        try {
            return ($e = $this->mangEmployee) ? $read($e) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /* ---------- الأدوار والصلاحيات الإدارية ---------- */

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('granted_by')->withTimestamps();
    }

    /** @return list<string> */
    public function permissions(): array
    {
        if ($this->is_admin) {
            return array_column(Permission::cases(), 'value');
        }

        return $this->permissionsCache ??= RolePermission::query()
            ->whereIn('role_id', $this->roles()->select('roles.id'))
            ->distinct()->pluck('permission')->all();
    }

    /** مدير النظام (is_admin) يملك كل الصلاحيات الإدارية. */
    public function hasPermission(Permission|string $permission): bool
    {
        $value = $permission instanceof Permission ? $permission->value : $permission;

        return $this->is_active && in_array($value, $this->permissions(), true);
    }

    public function hasAnyAdminPermission(): bool
    {
        return $this->is_active && $this->permissions() !== [];
    }

    public function forgetPermissions(): void
    {
        $this->permissionsCache = null;
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class);
    }

    public function activeAssignments(): HasMany
    {
        return $this->assignments()->active();
    }

    public function sentLetters(): HasMany
    {
        return $this->hasMany(Letter::class, 'sender_user_id');
    }

    /* ---------- الجهات الحالية والرؤية ---------- */

    /** الجهات التي يمثلها المستخدم الآن (عادة واحدة، وقد تزيد مع الإنابة). */
    public function currentUnits(): Collection
    {
        return $this->currentUnitsCache ??= Unit::query()
            ->whereIn('id', $this->activeAssignments()->select('unit_id'))
            ->get();
    }

    /** @return list<int> */
    public function currentUnitIds(): array
    {
        return $this->currentUnits()->modelKeys();
    }

    /** @return list<string> */
    public function currentUnitPaths(): array
    {
        return $this->currentUnits()->pluck('path')->all();
    }

    /** الجهة الأساسية المعروضة للمستخدم في الواجهة (أول جهة مسندة). */
    public function primaryUnit(): ?Unit
    {
        return $this->currentUnits()->first();
    }

    public function isUnitUser(): bool
    {
        return $this->currentUnits()->isNotEmpty();
    }

    public function holdsUnit(Unit|int $unit): bool
    {
        return in_array($unit instanceof Unit ? $unit->id : $unit, $this->currentUnitIds(), true);
    }

    /**
     * مسارات الجذر حتى كل جهة يمثلها المستخدم أو يعمل بها — لمطابقة التعاميم الموجهة لفرع.
     *
     * @return list<string>
     */
    public function ancestorPathsForBroadcast(): array
    {
        $paths = $this->currentUnitPaths();
        if ($this->homeUnit) {
            $paths[] = $this->homeUnit->path;
        }

        return array_values(array_unique(array_merge(...array_map(Unit::pathChain(...), $paths ?: ['/']))));
    }

    /** @return list<string> أنواع الجهات التي يمثلها أو يعمل بها */
    public function unitTypesForBroadcast(): array
    {
        $units = $this->currentUnits();
        if ($this->homeUnit) {
            $units = $units->concat([$this->homeUnit]);
        }

        return $units->map(fn (Unit $u) => $u->type->value)->unique()->values()->all();
    }

    public function forgetCurrentUnits(): void
    {
        $this->currentUnitsCache = null;
    }
}
