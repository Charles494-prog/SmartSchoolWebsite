<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['date_hired' => 'date', 'monthly_salary' => 'float'];

    public const TYPES = ['regular' => 'Regular', 'probationary' => 'Probationary', 'contractual' => 'Contractual', 'part_time' => 'Part-time'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getFullNameAttribute(): string
    {
        return $this->last_name.', '.$this->first_name;
    }
}
