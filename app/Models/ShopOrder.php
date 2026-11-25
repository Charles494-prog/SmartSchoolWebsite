<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ShopOrder extends Model
{
    /** placed → (bookstore approves) approved → (finance confirms payment) paid → released. */
    public const STATUSES = ['placed' => 'Waiting for bookstore', 'approved' => 'Approved – please pay', 'paid' => 'Paid – ready to release', 'released' => 'Released', 'cancelled' => 'Not accepted'];

    public function isPaid(): bool
    {
        return $this->charge && $this->charge->remaining() <= 0;
    }

    protected $guarded = ['id'];

    protected $casts = ['total' => 'float'];

    public function lines(): HasMany
    {
        return $this->hasMany(ShopOrderLine::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function charge(): HasOne
    {
        return $this->hasOne(AccountCharge::class);
    }

    public function summary(): string
    {
        return $this->lines->map(fn ($l) => $l->name.($l->size ? ' ('.$l->size.')' : '').($l->qty > 1 ? ' ×'.$l->qty : ''))->implode(', ');
    }
}
