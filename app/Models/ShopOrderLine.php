<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopOrderLine extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['price' => 'float'];
}
