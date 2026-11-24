<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subject extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function weights(): array
    {
        return ['ww' => (int) $this->ww_weight, 'pt' => (int) $this->pt_weight, 'qa' => (int) $this->qa_weight, 'at' => (int) $this->at_weight];
    }
}
