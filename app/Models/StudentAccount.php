<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\Billing;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentAccount extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['gross_amount' => 'float', 'discount_amount' => 'float', 'net_amount' => 'float'];

    public function planName(): string
    {
        return Billing::plan((string) $this->payment_plan)?->name ?? 'Custom';
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class)->orderBy('due_on');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(AccountDiscount::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->where('is_void', false)->sum('amount');
    }

    public function balance(): float
    {
        return round($this->net_amount - $this->paidAmount(), 2);
    }

    /** Amount that should have been paid by today but was not. */
    public function overdueAmount(?CarbonInterface $today = null): float
    {
        $today ??= now();
        $due = (float) $this->installments()->whereDate('due_on', '<=', $today)->sum('amount');

        return max(0, round($due - $this->paidAmount(), 2));
    }
}
