<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Section;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class CommunicationController extends Controller
{
    // ------------------------------------------------------------ announcements

    public function announcements(Request $request)
    {
        $u = $request->user();

        return view('messages.announcements', [
            'items' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? Announcement::with('author', 'section')->latest()->paginate(20)
                : Announcement::with('author', 'section')->visibleTo($u)->orderByDesc('is_pinned')->latest('published_at')->paginate(20),
            'canPost' => $u->hasRole(Role::ADMIN, Role::REGISTRAR, Role::TEACHER),
            'sections' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? Section::with('gradeLevel')->whereHas('schoolYear', fn ($q) => $q->where('is_current', true))->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()])
                : Section::with('gradeLevel')->whereIn('id', $u->teacherSectionIds())->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
        ]);
    }

    public function postAnnouncement(Request $request)
    {
        $u = $request->user();
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR, Role::TEACHER), 403);
        $data = $request->validate([
            'title' => 'required|max:150',
            'body' => 'required|max:5000',
            'audience' => 'required|in:'.implode(',', array_keys(Announcement::AUDIENCES)),
            'section_id' => 'nullable|exists:sections,id',
            'is_pinned' => 'boolean',
            'email' => 'boolean',
        ]);
        if ($u->role === Role::TEACHER) {
            // Teachers post to their own sections only.
            abort_unless(! empty($data['section_id']) && in_array((int) $data['section_id'], $u->teacherSectionIds(), true), 403, 'Choose one of your sections.');
        }
        $a = Announcement::create(collect($data)->except('email')->all() + ['author_id' => $u->id, 'published_at' => now()]);
        $recipients = User::where('is_active', true)->where('id', '!=', $u->id)->get()->filter(fn ($r) => Announcement::whereKey($a->id)->visibleTo($r)->exists());
        Notification::send($recipients, new GeneralNotice($a->title, Str::limit($a->body, 200), '/announcements', $request->boolean('email')));

        return back()->with('ok', 'Announcement posted to '.$recipients->count().' user(s).');
    }

    public function deleteAnnouncement(Announcement $announcement)
    {
        abort_unless(auth()->user()->isAdmin() || $announcement->author_id === auth()->id(), 403);
        $announcement->delete();

        return back()->with('ok', 'Announcement deleted.');
    }

    // ------------------------------------------------------------ calendar

    public function calendar(Request $request)
    {
        $month = Carbon::parse(($request->query('month') ?: now()->format('Y-m')).'-01');
        $start = $month->copy()->startOfMonth()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $events = Event::where(fn ($q) => $q->whereBetween('starts_on', [$start, $end])->orWhereBetween('ends_on', [$start, $end]))->orderBy('starts_on')->get();

        return view('calendar.index', ['month' => $month, 'start' => $start, 'end' => $end, 'events' => $events]);
    }

    // ------------------------------------------------------------ notifications

    public function notifications(Request $request)
    {
        $list = $request->user()->notifications()->paginate(30);
        $request->user()->unreadNotifications->markAsRead();

        return view('messages.notifications', ['list' => $list]);
    }
}
