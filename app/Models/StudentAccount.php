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

    /** The fee lines this student was charged – the one source for every fee figure. */
    public function fees()
    {
        return $this->hasMany(AccountFee::class);
    }

    public function charges()
    {
        return $this->hasMany(AccountCharge::class)->where('is_void', false);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments()->where('is_void', false)->sum('amount');
    }

    /** Money that went to bookstore orders and other charges (not tuition). */
    public function paidToCharges(): float
    {
        return (float) PaymentAllocation::whereNotNull('account_charge_id')->whereHas('payment', fn ($p) => $p->where('student_account_id', $this->id)->where('is_void', false))->sum('amount');
    }

    /** Tuition & fees paid (everything not set aside for an order). */
    public function tuitionPaid(): float
    {
        return round($this->paidAmount() - $this->paidToCharges(), 2);
    }

    public function tuitionBalance(): float
    {
        return max(0, round($this->net_amount - $this->tuitionPaid(), 2));
    }

    public function chargesTotal(): float
    {
        return (float) $this->charges()->sum('amount');
    }

    /** Everything still owed: tuition plus unpaid orders/charges. */
    public function balance(): float
    {
        return round($this->net_amount + $this->chargesTotal() - $this->paidAmount(), 2);
    }

    /** Tuition that should have been paid by today but was not. */
    public function overdueAmount(?CarbonInterface $today = null): float
    {
        $today ??= now();
        $due = (float) $this->installments()->whereDate('due_on', '<=', $today)->sum('amount');

        return max(0, round($due - $this->tuitionPaid(), 2));
    }
}
