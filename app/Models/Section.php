<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function adviser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adviser_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classSubjects(): HasMany
    {
        return $this->hasMany(ClassSubject::class);
    }

    /** Students currently enrolled, alphabetical (boys first). */
    public function students()
    {
        return Student::whereIn('id', $this->enrollments()->where('status', 'enrolled')->select('student_id'))
            ->orderByRaw("CASE WHEN sex = 'Male' THEN 0 ELSE 1 END")
            ->orderBy('last_name')->orderBy('first_name');
    }

    public function label(): string
    {
        return ($this->gradeLevel?->name ? $this->gradeLevel->name.' – ' : '').$this->name;
    }
}
