<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GuidanceRecord extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['recorded_on' => 'date', 'is_confidential' => 'boolean'];

    public const TYPES = ['counseling' => 'Counseling', 'incident' => 'Incident report', 'referral' => 'Referral', 'follow_up' => 'Follow-up'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
