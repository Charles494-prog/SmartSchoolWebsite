<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradeLevel extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['numeric_grades' => 'boolean'];

    public const DEPARTMENTS = ['kinder' => 'Kindergarten', 'elementary' => 'Elementary', 'jhs' => 'Junior High School', 'shs' => 'Senior High School', 'college' => 'College', 'other' => 'Other'];

    public function next(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class, 'next_grade_level_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }
}
