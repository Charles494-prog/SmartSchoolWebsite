<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradingRequest extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['reviewed_at' => 'datetime'];

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function weights(): array
    {
        return ['ww' => $this->ww_weight, 'pt' => $this->pt_weight, 'qa' => $this->qa_weight, 'at' => $this->at_weight];
    }
}
