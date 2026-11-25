<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicVisit extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['visited_at' => 'datetime', 'sent_home' => 'boolean', 'parent_notified' => 'boolean'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Who visited: the student's or the staff member's name. */
    public function getPatientNameAttribute(): string
    {
        return $this->student?->full_name ?? ($this->employee ? $this->employee->full_name.' (staff)' : '—');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
