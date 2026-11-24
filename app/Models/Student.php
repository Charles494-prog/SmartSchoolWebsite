<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Student extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['birthdate' => 'date'];

    protected static function booted(): void
    {
        static::creating(function (Student $s) {
            $s->qr_token ??= Str::random(32);
            $s->student_no ??= static::nextStudentNo();
        });
    }

    public static function nextStudentNo(): string
    {
        $prefix = now()->format('Y').'-';
        $last = static::where('student_no', 'like', $prefix.'%')->max('student_no');
        $n = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $n, 5, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class)->withPivot(['relationship', 'is_primary', 'is_emergency_contact']);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function currentEnrollment(): ?Enrollment
    {
        $sy = SchoolYear::current();

        return $sy ? $this->enrollments()->where('school_year_id', $sy->id)->with(['section', 'gradeLevel'])->first() : null;
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->last_name.', '.$this->first_name.' '.($this->middle_name ? mb_substr($this->middle_name, 0, 1).'.' : '').' '.($this->suffix ?? ''));
    }

    public function getAgeAttribute(): int
    {
        return $this->birthdate->age;
    }
}
