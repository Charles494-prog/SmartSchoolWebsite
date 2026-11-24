<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use Auditable;

    protected $guarded = ['id'];

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
