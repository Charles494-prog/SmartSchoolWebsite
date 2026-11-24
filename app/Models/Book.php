<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function available(): int
    {
        return $this->copies - $this->loans()->whereNull('returned_on')->count();
    }
}
