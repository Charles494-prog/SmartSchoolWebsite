<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class PaymentPlan extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean', 'downpayment_percent' => 'float', 'surcharge_percent' => 'float'];

    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('installments')->pluck('name', 'id')->all();
    }
}
