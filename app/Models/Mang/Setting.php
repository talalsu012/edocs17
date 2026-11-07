<?php

namespace App\Models\Mang;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * mang.employee_settings — جدول الترميزات العام (كليات، أقسام، مسميات وظيفية...).
 * الربط بين الجداول يتم بالعمود code مع تحديد group.
 */
class Setting extends ReadOnlyMangModel
{
    protected $table = 'employee_settings';

    protected $casts = ['is_active' => 'boolean'];

    public function scopeGroup(Builder $q, string $key): void
    {
        $q->where('group', config("edocs.mang.groups.$key", $key));
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, config('edocs.mang.parent_column'), 'code');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, config('edocs.mang.parent_column'), 'code');
    }

    public function parentCode(): ?string
    {
        $v = $this->getAttribute(config('edocs.mang.parent_column'));

        return $v === null || $v === '' ? null : (string) $v;
    }
}
