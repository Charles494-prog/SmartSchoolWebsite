<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    /** name => "Room 101 · 40 seats" for dropdowns. */
    public static function options(): array
    {
        return static::where('is_active', true)->orderBy('name')->get()
            ->mapWithKeys(fn ($r) => [$r->name => $r->name.($r->building ? ' ('.$r->building.')' : '').' · '.$r->capacity.' seats'])->all();
    }

    /** name => capacity, for auto-filling section capacity. */
    public static function capacities(): array
    {
        return static::where('is_active', true)->pluck('capacity', 'name')->all();
    }
}
