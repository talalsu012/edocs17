<?php

namespace App\Models\Mang;

use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * mang.employees — المفتاح الوظيفي employee_id هو الرقم الوظيفي (users.employee_no في edocs).
 */
class Employee extends ReadOnlyMangModel
{
    protected $table = 'employees';

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    protected $keyType = 'string';

    public function organization(): HasOne
    {
        return $this->hasOne(Organization::class, 'employee_id', 'employee_id')
            ->whereNull('end_date')
            ->latest('start_date');
    }

    public function job(): HasOne
    {
        return $this->hasOne(Job::class, 'employee_id', 'employee_id')->where('is_active', true);
    }

    public function contact(): HasOne
    {
        return $this->hasOne(Contact::class, 'employee_id', 'employee_id');
    }

    public function displayName(): string
    {
        return trim($this->full_name ?: implode(' ', array_filter([
            $this->first_name, $this->second_name, $this->third_name, $this->fourth_name,
        ])));
    }

    public function jobTitle(): ?string
    {
        return $this->job?->jobTitle?->name_ar;
    }
}
