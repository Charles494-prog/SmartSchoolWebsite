<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizQuestion extends Model
{
    public const TYPES = [
        'mc' => 'Multiple choice', 'multi' => 'Checkboxes (many answers)', 'tf' => 'True / False', 'short' => 'Short answer',
        'numeric' => 'Number', 'matching' => 'Matching', 'essay' => 'Essay (checked by teacher)',
    ];

    protected $guarded = ['id'];

    protected $casts = ['data' => 'array', 'points' => 'float'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}
