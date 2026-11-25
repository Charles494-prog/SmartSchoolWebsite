<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolService extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['fee' => 'float', 'is_active' => 'boolean'];

    public const DEFAULTS = [
        ['Transcript of Records (TOR / Form 137)', 'Official copy of your grades', 150, 5],
        ['Certificate of Good Moral Character', null, 50, 2],
        ['Certificate of Enrollment', null, 50, 1],
        ['Copy of diploma', 'Certified true copy', 100, 3],
        ['Report card copy (SF9)', null, 0, 1],
    ];
}
