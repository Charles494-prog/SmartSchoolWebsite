<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use Auditable;

    protected $guarded = ['id'];

    protected $casts = ['published_at' => 'datetime', 'is_pinned' => 'boolean'];

    public const AUDIENCES = ['all' => 'Everyone', 'staff' => 'All staff', 'teachers' => 'Teachers', 'students' => 'Students', 'parents' => 'Parents'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    /** Announcements this user may see. */
    public function scopeVisibleTo($q, User $user)
    {
        $aud = ['all'];
        if ($user->isStaff()) {
            $aud[] = 'staff';
        }
        if ($user->role === 'teacher') {
            $aud[] = 'teachers';
        }
        if ($user->role === 'student') {
            $aud[] = 'students';
        }
        if ($user->role === 'parent') {
            $aud[] = 'parents';
        }
        $q->whereNotNull('published_at')->where('published_at', '<=', now())->whereIn('audience', $aud);
        if (! $user->isStaff()) {
            $sections = $user->visibleSectionIds();
            $q->where(fn ($w) => $w->whereNull('section_id')->orWhereIn('section_id', $sections));
        }

        return $q;
    }
}
