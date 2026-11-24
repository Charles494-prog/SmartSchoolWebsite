<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['value' => 'float', 'tuition_only' => 'boolean', 'is_scholarship' => 'boolean'];
}
