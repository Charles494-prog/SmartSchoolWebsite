<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationDocument extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public const TYPES = ['birth_certificate' => 'Birth certificate', 'sf10' => 'Records from previous school', 'report_card' => 'Latest report card', 'good_moral' => 'Good moral certificate', 'photo' => 'ID photo', 'other' => 'Other document'];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
