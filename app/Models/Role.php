<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'label', 'description', 'is_system'])]
class Role extends Model
{
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('granted_by')->withTimestamps();
    }

    public function permissionRows(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissionRows->pluck('permission')->all();
    }

    /** @param  array<string|Permission>  $permissions */
    public function syncPermissions(array $permissions): void
    {
        $values = array_values(array_intersect(
            array_map(fn ($p) => $p instanceof Permission ? $p->value : $p, $permissions),
            array_column(Permission::cases(), 'value'),
        ));

        $this->permissionRows()->whereNotIn('permission', $values)->delete();
        foreach ($values as $v) {
            $this->permissionRows()->firstOrCreate(['permission' => $v]);
        }
        $this->unsetRelation('permissionRows');
    }
}
