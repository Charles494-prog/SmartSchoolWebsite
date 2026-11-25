<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionAllocation extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['options' => 'array', 'plan' => 'array', 'locked' => 'array', 'previous' => 'array', 'deadline' => 'datetime', 'committed_at' => 'datetime', 'undone_at' => 'datetime'];

    public const METHODS = ['random' => 'Randomize', 'choice' => 'Students choose', 'manual' => 'Manual'];

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function sections()
    {
        return Section::where('school_year_id', $this->school_year_id)->where('grade_level_id', $this->grade_level_id)->orderBy('name');
    }

    /** Students to place: everyone enrolled in this grade and year (promoted, retained, new applicants, transferees). */
    public function enrollments()
    {
        return Enrollment::with('student.guardians')->where('school_year_id', $this->school_year_id)->where('grade_level_id', $this->grade_level_id)->where('status', 'enrolled');
    }
}
