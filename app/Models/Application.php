<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['birthdate' => 'date', 'consent_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public const STATUSES = ['pending' => 'Pending', 'for_review' => 'For review', 'approved' => 'Approved', 'rejected' => 'Rejected'];

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function schoolYear(): BelongsTo
    {
        return $this->belongsTo(SchoolYear::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->last_name.', '.$this->first_name.' '.($this->middle_name ?? ''));
    }
}
