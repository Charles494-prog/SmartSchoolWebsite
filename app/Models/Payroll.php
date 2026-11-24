<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payroll extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date'];

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }
}
