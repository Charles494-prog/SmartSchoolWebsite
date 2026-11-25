<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Award extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['is_active' => 'boolean'];

    /** Suggested awards for a new school; the admin can rename, add or turn them off. */
    public const DEFAULTS = [
        ['Best in Conduct', 'Shows respect, honesty and good behavior every day'],
        ['Leadership Award', 'Leads classmates and school activities well'],
        ['Most Helpful', 'Always ready to help classmates and teachers'],
        ['Best in Reading', 'Reads widely and shares what they learn'],
        ['Outstanding in Sports', 'Represents the class or school in sports'],
        ['Outstanding in the Arts', 'Shines in music, dance, drawing or theater'],
    ];
}
