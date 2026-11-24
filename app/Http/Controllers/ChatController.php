<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\GeneralNotice;
use App\Services\Chat;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** JSON endpoints behind the floating chat window. */
class ChatController extends Controller
{
    private function mine(Conversation $c): void
    {
        abort_unless($c->users()->where('users.id', auth()->id())->exists(), 403);
    }

    private function person(User $u): array
    {
        $initials = collect(explode(' ', $u->name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('');

        return ['id' => $u->id, 'name' => $u->name, 'role' => $u->roleLabel(), 'initials' => mb_strtoupper($initials)];
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $convs = $u->belongsToMany(Conversation::class)->withPivot('last_read_at')
            ->with(['users', 'messages' => fn ($q) => $q->latest()->limit(1)])->orderByDesc('conversations.updated_at')->limit(50)->get();

        return response()->json([
            'conversations' => $convs->map(function ($c) use ($u) {
                $other = $c->users->firstWhere('id', '!=', $u->id) ?? $u;
                $last = $c->messages->first();

                return [
                    'id' => $c->id,
                    'with' => $this->person($other),
                    'last' => $last ? ($last->user_id === $u->id ? 'You: ' : '').$last->body : '',
                    'time' => $last?->created_at->diffForHumans(short: true),
                    'unread' => $last && $last->user_id !== $u->id && (! $c->pivot->last_read_at || $last->created_at > $c->pivot->last_read_at),
                ];
            })->values(),
            'unread' => $convs->filter(fn ($c) => ($l = $c->messages->first()) && $l->user_id !== $u->id && (! $c->pivot->last_read_at || $l->created_at > $c->pivot->last_read_at))->count(),
        ]);
    }

    public function contacts(Request $request)
    {
        $q = mb_strtolower(trim((string) $request->query('q')));

        return response()->json(Chat::contacts($request->user())
            ->filter(fn ($c) => $q === '' || str_contains(mb_strtolower($c->name.' '.$c->roleLabel()), $q))
            ->take(30)->map(fn ($c) => $this->person($c))->values());
    }

    public function start(Request $request)
    {
        $u = $request->user();
        $other = User::findOrFail($request->validate(['to' => 'required|integer'])['to']);
        abort_unless(Chat::allowed($u, $other), 403, 'You cannot message this person.');
        $c = Conversation::whereHas('users', fn ($q) => $q->where('users.id', $u->id))
            ->whereHas('users', fn ($q) => $q->where('users.id', $other->id))->has('users', '=', 2)->first();
        if (! $c) {
            $c = Conversation::create(['subject' => '']);
            $c->users()->attach($u->id, ['last_read_at' => now()]);
            $c->users()->attach($other->id, ['last_read_at' => null]);
        }

        return response()->json(['id' => $c->id, 'with' => $this->person($other)]);
    }

    public function messages(Request $request, Conversation $conversation)
    {
        $this->mine($conversation);
        $u = $request->user();
        $conversation->users()->updateExistingPivot($u->id, ['last_read_at' => now()]);
        $msgs = $conversation->messages()->where('id', '>', (int) $request->query('after', 0))->limit(200)->get();

        return response()->json($msgs->map(fn (Message $m) => [
            'id' => $m->id, 'mine' => $m->user_id === $u->id, 'body' => $m->body, 'time' => $m->created_at->format('M j, g:i A'),
        ]));
    }

    public function send(Request $request, Conversation $conversation)
    {
        $this->mine($conversation);
        $u = $request->user();
        $others = $conversation->users()->where('users.id', '!=', $u->id)->get();
        foreach ($others as $o) {
            abort_unless(Chat::allowed($u, $o), 403, 'You cannot message this person.');
        }
        $body = $request->validate(['body' => 'required|string|max:5000'])['body'];
        $m = $conversation->messages()->create(['user_id' => $u->id, 'body' => trim($body)]);
        $conversation->touch();
        $conversation->users()->updateExistingPivot($u->id, ['last_read_at' => now()]);
        foreach ($others as $o) {
            $o->notify(new GeneralNotice('Message from '.$u->name, Str::limit($m->body, 120), '/messages'));
        }

        return response()->json(['id' => $m->id, 'mine' => true, 'body' => $m->body, 'time' => $m->created_at->format('M j, g:i A')]);
    }
}
