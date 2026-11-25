<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['order' => 'array', 'answers' => 'array', 'marks' => 'array', 'score' => 'float', 'needs_review' => 'boolean', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** When time runs out (null = no limit). */
    public function deadline(): ?\Carbon\Carbon
    {
        $m = $this->assignment->time_limit;

        return $m ? $this->started_at->copy()->addMinutes($m) : null;
    }
}
