<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assignment extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['due_at' => 'datetime', 'max_score' => 'float', 'shuffle_questions' => 'boolean', 'shuffle_answers' => 'boolean', 'allow_comments' => 'boolean', 'track_reads' => 'boolean'];

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class)->oldest();
    }

    public function reads(): HasMany
    {
        return $this->hasMany(PostRead::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function classSubjectSection(): ?int
    {
        return $this->classSubject?->section_id;
    }
}
