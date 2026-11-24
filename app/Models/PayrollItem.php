<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['basic_pay' => 'float', 'allowances' => 'float', 'sss' => 'float', 'philhealth' => 'float', 'pagibig' => 'float', 'withholding_tax' => 'float', 'other_deductions' => 'float', 'net_pay' => 'float'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }
}
