<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One fee line a student was charged (copied from the fee items when fees were assessed). */
class AccountFee extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float'];
}
