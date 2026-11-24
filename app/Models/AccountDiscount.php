<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountDiscount extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public $timestamps = false;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
