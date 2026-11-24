<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Installment extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['due_on' => 'date', 'amount' => 'float'];
}
