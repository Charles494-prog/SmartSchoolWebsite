<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookLoan extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['borrowed_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date', 'fine' => 'float'];

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function borrowerName(): string
    {
        return $this->student?->full_name ?? $this->user?->name ?? '—';
    }

    public function isOverdue(): bool
    {
        return ! $this->returned_on && $this->due_on->isPast() && ! $this->due_on->isToday();
    }
}
