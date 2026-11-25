<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopItem extends Model
{
    public const CATEGORIES = ['book' => 'Books', 'uniform' => 'Uniforms', 'merch' => 'Merch', 'souvenir' => 'Souvenirs', 'other' => 'Other'];

    protected $guarded = ['id'];

    protected $casts = ['price' => 'float', 'sizes' => 'array', 'is_active' => 'boolean'];
}
