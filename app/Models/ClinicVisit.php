<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClinicVisit extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['visited_at' => 'datetime', 'sent_home' => 'boolean', 'parent_notified' => 'boolean'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
