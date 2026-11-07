<?php

namespace App\Models\Mang;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** mang.employee_jobs — الوظيفة النشطة والمسمى الوظيفي. */
class Job extends ReadOnlyMangModel
{
    protected $table = 'employee_jobs';

    protected $casts = ['is_active' => 'boolean'];

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'job_title_code', 'code')->group('job_title');
    }
}
