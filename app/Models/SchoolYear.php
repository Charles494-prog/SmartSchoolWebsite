<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolYear extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['enrollment_opens_on' => 'date', 'starts_on' => 'date', 'ends_on' => 'date', 'is_current' => 'boolean'];

    public static function current(): ?self
    {
        return static::where('is_current', true)->first() ?? static::latest('starts_on')->first();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
