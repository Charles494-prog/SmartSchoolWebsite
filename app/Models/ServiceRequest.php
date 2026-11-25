<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    public const STATUSES = ['requested' => 'Requested', 'processing' => 'Being prepared', 'released' => 'Released', 'declined' => 'Not accepted'];

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
}
