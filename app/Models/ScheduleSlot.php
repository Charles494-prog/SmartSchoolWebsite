<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Timetable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleSlot extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const DAYS = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** A room, teacher or section can never be double-booked – whatever screen or script saves the slot. */
    protected static function booted(): void
    {
        static::saving(fn (self $slot) => Timetable::guard($slot));
    }

    public function classSubject(): BelongsTo
    {
        return $this->belongsTo(ClassSubject::class);
    }
}
