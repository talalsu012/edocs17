<?php

namespace App\Models\Mang;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** mang.employee_organizations — الكلية والقسم الحاليان للموظف. */
class Organization extends ReadOnlyMangModel
{
    protected $table = 'employee_organizations';

    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'faculty_code', 'code')->group('faculty');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Setting::class, 'department_code', 'code')->group('department');
    }
}
