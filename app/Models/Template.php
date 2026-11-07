<?php

namespace App\Models;

use App\Enums\TemplateAudience;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * نموذج خطاب بإصدارات. التعديل ينشئ إصدارًا جديدًا عبر newVersion().
 */
#[Fillable([
    'code', 'version', 'name', 'category', 'subject_pattern', 'body_with_fields', 'fields_json',
    'default_recipient_unit_id', 'allowed_for', 'is_active', 'created_by',
])]
class Template extends Model
{
    protected function casts(): array
    {
        return [
            'fields_json' => 'array',
            'allowed_for' => TemplateAudience::class,
            'is_active' => 'boolean',
        ];
    }

    public function defaultRecipientUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'default_recipient_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function letters(): HasMany
    {
        return $this->hasMany(Letter::class);
    }

    /** آخر إصدار نشط لكل نموذج. */
    public function scopeLatestActive(Builder $q): void
    {
        $q->where('is_active', true)->whereIn('id', static::query()
            ->selectRaw('max(id)')->where('is_active', true)->groupBy('code'));
    }

    public function scopeForStaff(Builder $q): void
    {
        $q->whereIn('allowed_for', [TemplateAudience::Staff->value, TemplateAudience::Both->value]);
    }

    public function scopeForUnits(Builder $q): void
    {
        $q->whereIn('allowed_for', [TemplateAudience::Unit->value, TemplateAudience::Both->value]);
    }

    /** أسماء الحقول الموجودة في النص بين {{ }} */
    public function placeholders(): array
    {
        preg_match_all('/\{\{\s*([^}\s]+)\s*\}\}/u', $this->body_with_fields.' '.$this->subject_pattern, $m);

        return array_values(array_unique($m[1]));
    }

    /** تعبئة النص بالقيم؛ الحقول الناقصة تبقى فارغة. */
    public function render(array $values, ?string $text = null): string
    {
        return preg_replace_callback('/\{\{\s*([^}\s]+)\s*\}\}/u',
            fn ($m) => (string) ($values[$m[1]] ?? ''), $text ?? $this->body_with_fields);
    }

    public function renderSubject(array $values): string
    {
        return $this->render($values, $this->subject_pattern ?? $this->name);
    }

    /** إنشاء إصدار جديد بالتعديلات وتعطيل الإصدار الحالي. */
    public function newVersion(array $changes): static
    {
        $next = $this->replicate(['created_at', 'updated_at']);
        $next->fill($changes);
        $next->version = static::where('code', $this->code)->max('version') + 1;
        $next->is_active = true;
        $next->save();

        $this->update(['is_active' => false]);

        return $next;
    }
}
