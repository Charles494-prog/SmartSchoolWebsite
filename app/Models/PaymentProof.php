<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentProof extends Model
{
    protected $guarded = ['id'];

    /** Purpose can list several items – keep it within the column (120). */
    public function setPurposeAttribute($v): void
    {
        $this->attributes['purpose'] = $v === null ? null : \Illuminate\Support\Str::limit((string) $v, 117);
    }

    protected $casts = ['paid_on' => 'date', 'amount' => 'float'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(StudentAccount::class, 'student_account_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(PaymentChannel::class, 'payment_channel_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
