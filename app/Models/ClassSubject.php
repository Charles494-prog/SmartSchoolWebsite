<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClassSubject extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(ScheduleSlot::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(GradeSubmission::class);
    }

    /** This class's own weights if the admin approved a change, otherwise the subject's. */
    public function weights(): array
    {
        if ($this->ww_weight !== null) {
            return ['ww' => (int) $this->ww_weight, 'pt' => (int) $this->pt_weight, 'qa' => (int) $this->qa_weight, 'at' => (int) $this->at_weight];
        }

        return $this->subject->weights();
    }

    public function hasCustomWeights(): bool
    {
        return $this->ww_weight !== null;
    }

    public function gradingRequests(): HasMany
    {
        return $this->hasMany(GradingRequest::class);
    }

    public function isLocked(int $term): bool
    {
        return $this->submissions()->where('term', $term)->whereIn('status', ['submitted', 'approved'])->exists();
    }

    public function label(): string
    {
        return $this->subject->name.' · '.$this->section->label();
    }
}
