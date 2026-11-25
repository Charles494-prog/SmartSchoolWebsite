<?php

namespace App\Models;

use App\Notifications\GeneralNotice;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Notification;

class ServiceRequest extends Model
{
    public const STATUSES = ['requested' => 'Requested', 'processing' => 'Being prepared', 'ready' => 'Ready for release', 'released' => 'Released', 'declined' => 'Not accepted'];

    public function isPaid(): bool
    {
        return ! $this->charge || $this->charge->remaining() <= 0;
    }

    protected $guarded = ['id'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(SchoolService::class, 'school_service_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(AccountCharge::class, 'account_charge_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
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
        Notification::send($people, new GeneralNotice('Your document is ready', $this->service->name.' is paid and ready. You can get it at the registrar\'s office now.', '/portal/services'));

        return $people->count();
    }
}
