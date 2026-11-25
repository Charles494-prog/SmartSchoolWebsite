<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    /** Purpose can list several items – keep it within the column (120). */
    public function setPurposeAttribute($v): void
    {
        $this->attributes['purpose'] = $v === null ? null : \Illuminate\Support\Str::limit((string) $v, 117);
    }

    protected $casts = ['paid_on' => 'date', 'amount' => 'float', 'is_void' => 'boolean'];

    public const METHODS = ['cash' => 'Cash', 'gcash' => 'GCash / e-wallet', 'bank' => 'Bank transfer / deposit', 'check' => 'Check'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(StudentAccount::class, 'student_account_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
