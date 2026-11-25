<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\Section;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Support\NoticeFeed;
use App\Support\Role;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunicationController extends Controller
{
    // ------------------------------------------------------------ announcements

    public function announcements(Request $request)
    {
        $u = $request->user();

        $items = $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? Announcement::with('author', 'section', 'readers')->orderByDesc('is_pinned')->latest('published_at')->latest('id')->paginate(20)
            : Announcement::with('author', 'section', 'readers')->visibleTo($u)->orderByDesc('is_pinned')->latest('published_at')->paginate(20);
        // Opening the page counts as reading what's on it; remember which ones were new for this visit.
        $new = $items->getCollection()->reject(fn ($a) => $a->readers->contains('id', $u->id))->pluck('id');
        foreach ($new as $id) {
            DB::table('announcement_reads')->insertOrIgnore(['announcement_id' => $id, 'user_id' => $u->id, 'read_at' => now()]);
        }

        return view('messages.announcements', [
            'items' => $items, 'new' => $new,
            // School-wide announcements come from the office. Teachers post to their own classes from the class card.
            'canPost' => $u->hasRole(Role::ADMIN, Role::REGISTRAR),
            'sections' => $u->hasRole(Role::ADMIN, Role::REGISTRAR) ? Section::with('gradeLevel')->whereHas('schoolYear', fn ($q) => $q->where('is_current', true))->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()])
                : Section::with('gradeLevel')->whereIn('id', $u->teacherSectionIds())->get()->mapWithKeys(fn ($s) => [$s->id => $s->label()]),
        ]);
    }

    public function postAnnouncement(Request $request)
    {
        $u = $request->user();
        abort_unless($u->hasRole(Role::ADMIN, Role::REGISTRAR), 403, 'Teachers post announcements in their class cards.');
        $data = $request->validate([
            'title' => 'required|max:150',
            'body' => 'required|max:5000',
            'image' => 'nullable|image|max:8192', 'image_caption' => 'nullable|max:255', 'event_at' => 'nullable|date', 'body_html' => 'nullable|max:200000',
            'audience' => 'required|in:'.implode(',', array_keys(Announcement::AUDIENCES)),
            'section_id' => 'nullable|exists:sections,id',
            'is_pinned' => 'boolean',
            'email' => 'boolean',
        ]);
        if ($u->role === Role::TEACHER) {
            // Teachers post to their own sections only.
            abort_unless(! empty($data['section_id']) && in_array((int) $data['section_id'], $u->teacherSectionIds(), true), 403, 'Choose one of your sections.');
        }
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('announcements', 'local');
        }
        $a = Announcement::create(collect($data)->except(['email', 'image'])->all() + ['author_id' => $u->id, 'published_at' => now()]);
        $a->readers()->syncWithoutDetaching([$u->id => ['read_at' => now()]]);
        $recipients = User::where('is_active', true)->where('id', '!=', $u->id)->get()->filter(fn ($r) => Announcement::whereKey($a->id)->visibleTo($r)->exists());
        Notification::send($recipients, new GeneralNotice($a->title, Str::limit($a->body, 200), '/announcements', $request->boolean('email')));

        return back()->with('ok', 'Announcement posted to '.$recipients->count().' user(s).');
    }

    private function canSee(Announcement $a): bool
    {
        $u = auth()->user();

        return $u->hasRole(Role::ADMIN, Role::REGISTRAR) || Announcement::whereKey($a->id)->visibleTo($u)->exists();
    }

    public function readAnnouncement(Announcement $announcement)
    {
        abort_unless($this->canSee($announcement), 403);
        $announcement->readers()->syncWithoutDetaching([auth()->id() => ['read_at' => now()]]);

        return back();
    }

    public function announcementImage(Announcement $announcement)
    {
        abort_unless($this->canSee($announcement) && $announcement->image_path, 404);

        return Storage::disk('local')->response($announcement->image_path);
    }

    /** Designed content, shown in a sandboxed frame (see SecurityHeaders) – it can't affect the rest of the app. */
    public function announcementContent(Announcement $announcement)
    {
        abort_unless($this->canSee($announcement), 403);
        $resize = 'const s=()=>parent.postMessage({ssFrame:"a'.$announcement->id.'",h:document.documentElement.scrollHeight},"*");addEventListener("load",s);new ResizeObserver(s).observe(document.documentElement);';
        $code = (string) $announcement->body_html;
        $html = preg_match('/<html[\s>]/i', $code)
            ? (stripos($code, '</body>') !== false ? str_ireplace('</body>', '<script>'.$resize.'</script></body>', $code) : $code.'<script>'.$resize.'</script>')
            : '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>html,body{margin:0;font-family:system-ui,sans-serif}img,video,iframe{max-width:100%}</style></head><body>'.$code.'<script>'.$resize.'</script></body></html>';

        return response($html)->header('Content-Type', 'text/html; charset=utf-8')->header('X-SmartSchool-Sandbox', '1');
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
        $filter = array_key_exists($request->query('filter'), NoticeFeed::FILTERS) ? $request->query('filter') : 'all';
        $q = $request->user()->notifications();
        if ($filter === 'unread') {
            $q->whereNull('read_at');
        }
        $all = $q->get();   // type is derived from the text, so filter in PHP; a user's own notices are a short list
        if (in_array($filter, ['request', 'payment'], true)) {
            $all = $all->filter(fn ($n) => NoticeFeed::type($n) === $filter)->values();
        }
        $page = max(1, (int) $request->query('page', 1));
        $list = new LengthAwarePaginator($all->forPage($page, 30)->values(), $all->count(), 30, $page, ['path' => $request->url(), 'query' => $request->query()]);

        return view('messages.notifications', [
            'list' => $list, 'filter' => $filter,
            'groups' => NoticeFeed::grouped($list->getCollection()),
            'unread' => $request->user()->unreadNotifications()->count(),
            'readCount' => $request->user()->notifications()->whereNotNull('read_at')->count(),
        ]);
    }

    /** Open a notice: mark it read and go to the page it is about (same-site links only). */
    public function openNotification(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $url = (string) ($n->data['url'] ?? '/');
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $host = parse_url($url, PHP_URL_HOST);

        return redirect(! $host || $host === $request->getHost() ? $path.(($qs = parse_url($url, PHP_URL_QUERY)) ? '?'.$qs : '') : '/');
    }

    /** read / unread / delete on one or many of MY notices (others keep theirs). */
    public function updateNotifications(Request $request)
    {
        $d = $request->validate(['action' => 'required|in:read,unread,delete,read_all,clear_read', 'ids' => 'array|max:500', 'ids.*' => 'string|max:64']);
        $mine = $request->user()->notifications();
        $n = match ($d['action']) {
            'read' => (clone $mine)->whereIn('id', $d['ids'] ?? [])->update(['read_at' => now()]),
            'unread' => (clone $mine)->whereIn('id', $d['ids'] ?? [])->update(['read_at' => null]),
            'delete' => (clone $mine)->whereIn('id', $d['ids'] ?? [])->delete(),
            'read_all' => (clone $mine)->whereNull('read_at')->update(['read_at' => now()]),
            'clear_read' => (clone $mine)->whereNotNull('read_at')->delete(),
        };
        $unread = $request->user()->unreadNotifications()->count();
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'changed' => $n, 'unread' => $unread]);
        }
        $msg = ['read' => 'Marked as read.', 'unread' => 'Marked as unread.', 'delete' => "Deleted $n.", 'read_all' => 'All marked as seen.', 'clear_read' => "Cleared $n read ".str('notification')->plural($n).'.'][$d['action']];

        return back()->with('ok', $msg);
    }
}
