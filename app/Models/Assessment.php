<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Assessment extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['given_on' => 'date', 'max_score' => 'float'];

    public const COMPONENTS = ['ww' => 'Quiz / seatwork', 'pt' => 'Project / performance', 'qa' => 'Exam / summative'];

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class);
    }
}
