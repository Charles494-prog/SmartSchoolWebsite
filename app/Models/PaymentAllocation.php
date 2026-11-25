<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentAllocation extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'float'];

    /** When a payment finishes paying a document request that is already prepared, tell the registrar it can be released. */
    protected static function booted(): void
    {
        static::created(function (PaymentAllocation $a) {
            if (! $a->account_charge_id) {
                return;
            }
            $req = ServiceRequest::with('service', 'student', 'charge')->where('account_charge_id', $a->account_charge_id)->whereIn('status', ['processing', 'ready'])->first();
            if ($req && $req->isPaid()) {
                \Illuminate\Support\Facades\Notification::send(User::whereIn('role', ['registrar', 'admin'])->where('is_active', true)->get(),
                    new \App\Notifications\GeneralNotice('Paid – ready for releasing', $req->service->name.' for '.$req->student->full_name.' is paid.', route('services.manage', ['status' => $req->status], false)));
            }
        });
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
