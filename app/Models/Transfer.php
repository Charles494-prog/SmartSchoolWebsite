<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transfer extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['transfer_date' => 'date'];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
