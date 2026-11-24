<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = ['old_values' => 'array', 'new_values' => 'array', 'created_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(string $action, ?Model $model = null, array $old = [], array $new = [], ?string $description = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $model ? class_basename($model) : null,
            'auditable_id' => is_numeric($model?->getKey()) ? $model->getKey() : null,
            'description' => $description ?? (! is_numeric($model?->getKey()) ? $model?->getKey() : null),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => request()?->ip(),
            'created_at' => now(),
        ]);
    }
}
