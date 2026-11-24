<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date'];

    public const TYPES = ['event' => 'School event', 'exam' => 'Examination', 'holiday' => 'Holiday / No classes', 'meeting' => 'Meeting'];
}
