<?php

namespace App\Models;

use App\Notifications\GeneralNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Notification;

class ShopOrder extends Model
{
    /** placed → (bookstore approves) approved → (finance confirms payment) paid → released. */
    public const STATUSES = ['placed' => 'Waiting for bookstore', 'approved' => 'Approved – please pay', 'ready' => 'Prepared – ready to claim', 'paid' => 'Paid – ready to release', 'released' => 'Released', 'cancelled' => 'Not accepted'];

    public function isPaid(): bool
    {
        return $this->charge && $this->charge->remaining() <= 0;
    }

    protected $guarded = ['id'];

    protected $casts = ['total' => 'float', 'walk_in' => 'boolean'];

    /** Status as people see it: paid orders read "Paid – ready to release"; unpaid walk-ins read "Awaiting payment". */
    public function shownStatus(): string
    {
        if (in_array($this->status, ['approved', 'ready'], true) && $this->isPaid()) {
            return 'paid';
        }

        return $this->walk_in && $this->status === 'approved' ? 'awaiting' : $this->status;
    }

    public const SHOWN = ['placed' => 'New', 'approved' => 'Approved – waiting for payment', 'awaiting' => 'Awaiting payment (walk-in)', 'ready' => 'Prepared', 'paid' => 'Paid – ready to release', 'released' => 'Released', 'cancelled' => 'Not accepted'];

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

    /** The student's own account plus their parents' accounts. */
    public function family()
    {
        return collect([$this->student->user])->merge($this->student->guardians()->with('user')->get()->pluck('user'))->filter()->unique('id');
    }

    /** Paid and ready: tell the family to come get it. Returns how many accounts were told. */
    public function tellFamilyReady(): int
    {
        $people = $this->family();
        Notification::send($people, new GeneralNotice('Your order is ready', 'Bookstore order #'.$this->id.' ('.$this->summary().') is paid and ready. You can claim it at the bookstore now.', '/portal/shop'));

        return $people->count();
    }
}
