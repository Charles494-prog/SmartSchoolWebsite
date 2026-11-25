<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something added to a student's balance besides tuition – e.g. a bookstore order. */
class AccountCharge extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float', 'is_void' => 'boolean'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ShopOrder::class, 'shop_order_id');
    }

    public function paid(): float
    {
        return (float) PaymentAllocation::where('account_charge_id', $this->id)->whereHas('payment', fn ($p) => $p->where('is_void', false))->sum('amount');
    }

    public function remaining(): float
    {
        return $this->is_void ? 0 : max(0, round($this->amount - $this->paid(), 2));
    }
}
