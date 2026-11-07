<?php

namespace App\Models;

use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use InvalidArgumentException;

/**
 * جهة في شجرة الجامعة. path يُدار تلقائيًا (/1/4/17/) ويُعاد حسابه للفرع كله عند نقل الجهة.
 */
#[Fillable(['name', 'code', 'type', 'mang_group', 'mang_code', 'parent_id', 'head_title', 'can_broadcast_all', 'sort_order', 'is_active'])]
class Unit extends Model
{
    protected function casts(): array
    {
        return [
            'type' => UnitType::class,
            'can_broadcast_all' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Unit $unit) {
            if ($unit->exists && $unit->isDirty('parent_id') && $unit->parent_id) {
                $newParent = static::findOrFail($unit->parent_id);
                if ($newParent->id === $unit->id || str_starts_with($newParent->path, $unit->getOriginal('path'))) {
                    throw new InvalidArgumentException('لا يمكن نقل الجهة تحت نفسها أو تحت إحدى الجهات التابعة لها.');
                }
            }
        });

        static::created(function (Unit $unit) {
            $unit->rebuildPath();
        });

        static::updated(function (Unit $unit) {
            if ($unit->wasChanged('parent_id')) {
                $oldPath = $unit->path;
                $unit->rebuildPath();
                $unit->rebuildDescendantPaths($oldPath);
            }
        });
    }

    /* ---------- العلاقات ---------- */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Unit::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(UnitAssignment::class);
    }

    /**
     * التكليف النشط حاليًا. الأحدث يتقدم، فالإنابة المسجلة أثناء إجازة الأصيل
     * تظهر خلال فترتها ثم يعود الأصيل تلقائيًا بعد انتهائها.
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(UnitAssignment::class)->ofMany(
            ['id' => 'max'],
            fn (Builder $q) => $q->active(),
        );
    }

    /** الموظفون الذين جهة عملهم هذه الجهة. */
    public function staff(): HasMany
    {
        return $this->hasMany(User::class, 'home_unit_id');
    }

    public function holders(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unit_assignments')
            ->withPivot(['kind', 'start_date', 'end_date', 'decision_no'])
            ->withTimestamps();
    }

    public function sentLetters(): HasMany
    {
        return $this->hasMany(Letter::class, 'sender_unit_id');
    }

    public function receivedLetters(): HasMany
    {
        return $this->hasMany(LetterRecipient::class);
    }

    /* ---------- الشجرة ---------- */

    /** مستخدم الجهة الحالي (شاغل المنصب الآن). */
    public function currentHolder(): ?User
    {
        return $this->activeAssignment?->user;
    }

    /** كل الجهات تحت هذه الجهة (دون الجهة نفسها). */
    public function descendants(): Builder
    {
        return static::query()->where('path', 'like', $this->path.'%')->whereKeyNot($this->id);
    }

    /** الجهة وكل ما تحتها. */
    public function subtree(): Builder
    {
        return static::query()->where('path', 'like', $this->path.'%');
    }

    /** الأجداد مرتبين من الجذر نزولًا (دون الجهة نفسها). */
    public function ancestors(): Collection
    {
        $ids = $this->ancestorIds();

        return static::query()->whereIn('id', $ids)->orderBy('depth')->get();
    }

    /** @return list<int> */
    public function ancestorIds(): array
    {
        $ids = array_map('intval', array_filter(explode('/', $this->path)));
        array_pop($ids);

        return $ids;
    }

    public function isAncestorOf(Unit $other): bool
    {
        return $other->id !== $this->id && str_starts_with($other->path, $this->path);
    }

    /**
     * كل المسارات من الجذر حتى المسار المعطى: /1/4/17/ => [/1/, /1/4/, /1/4/17/]
     *
     * @return list<string>
     */
    public static function pathChain(string $path): array
    {
        $chain = [];
        $acc = '/';
        foreach (array_filter(explode('/', $path)) as $id) {
            $acc .= $id.'/';
            $chain[] = $acc;
        }

        return $chain;
    }

    /** هل الجهة مرتبطة بكلية/قسم في قاعدة الموظفين mang؟ */
    public function isLinkedToMang(): bool
    {
        return $this->mang_group !== null && $this->mang_code !== null;
    }

    /** الجهة المرتبطة بترميز في mang. */
    public static function forMang(string $group, ?string $code): ?self
    {
        if ($code === null || $code === '') {
            return null;
        }

        return static::query()->where('mang_group', $group)->where('mang_code', $code)->first();
    }

    public function scopeActive(Builder $q): void
    {
        $q->where('is_active', true);
    }

    public function scopeRoots(Builder $q): void
    {
        $q->whereNull('parent_id');
    }

    /** الاسم الكامل بالتسلسل: جامعة كسلا › كلية الهندسة › قسم الكهرباء */
    public function fullName(string $sep = ' › '): string
    {
        return $this->ancestors()->pluck('name')->push($this->name)->implode($sep);
    }

    /* ---------- داخلي ---------- */

    protected function rebuildPath(): void
    {
        $parent = $this->parent_id ? static::find($this->parent_id) : null;
        $this->path = ($parent ? $parent->path : '/').$this->id.'/';
        $this->depth = $parent ? $parent->depth + 1 : 0;
        $this->saveQuietly();
    }

    protected function rebuildDescendantPaths(string $oldPath): void
    {
        static::query()
            ->where('path', 'like', $oldPath.'%')
            ->whereKeyNot($this->id)
            ->orderBy('depth')
            ->each(function (Unit $child) use ($oldPath) {
                $child->path = $this->path.substr($child->path, strlen($oldPath));
                $child->depth = substr_count($child->path, '/') - 2;
                $child->saveQuietly();
            });
    }
}
