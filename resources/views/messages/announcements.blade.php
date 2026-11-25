@php
    $me = auth()->user();
    $initials = fn ($name) => collect(explode(' ', (string) $name))->filter()->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->implode('') ?: '?';
    $firstName = explode(' ', $me->name)[0];
@endphp
<x-layouts.app title="Announcements">
    <div class="mx-auto max-w-2xl">
        <h1 class="h1 mb-5">Announcements</h1>

        {{-- Composer: "What's on your mind?" card that opens into the full form --}}
        @if($canPost)
            <div class="card mb-5 p-4" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }">
                <div class="flex items-center gap-3">
                    @if($me->avatar_path)<img src="{{ route('profile.photo', $me) }}" class="h-10 w-10 shrink-0 rounded-full object-cover" alt="">@else<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials($me->name) }}</span>@endif
                    <button type="button" x-show="!open" x-on:click="open = true; $nextTick(() => $refs.title.querySelector('input').focus())"
                            class="min-h-11 flex-1 rounded-full bg-slate-100 px-4 text-left text-[15px] text-slate-500 transition hover:bg-slate-200">
                        What's on your mind, {{ $firstName }}?
                    </button>
                    <span x-show="open" x-cloak class="text-[15px] font-semibold text-slate-900">Create announcement</span>
                    <button type="button" x-show="open" x-cloak x-on:click="open = false" class="ml-auto flex h-11 w-11 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="Close"><x-icon name="x" class="h-5 w-5" /></button>
                </div>

                <form x-show="open" x-cloak x-transition.opacity.duration.250ms method="post" action="{{ route('announcements.store') }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-slate-100 pt-4"
                      x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf
                    <div x-ref="title"><x-field name="title" label="Title" required /></div>
                    <x-field name="body" label="Message" type="textarea" required />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="image" label="Picture (optional)" type="file" accept="image/*" />
                        <x-field name="image_caption" label="Picture caption" />
                    </div>
                    <x-field name="event_at" label="Event date and time (optional)" type="datetime-local" />
                    <details class="rounded-xl border border-slate-200 p-3 dark:border-slate-700">
                        <summary class="cursor-pointer text-sm font-semibold">Design it yourself (HTML / CSS / JS) – optional</summary>
                        <p class="mt-2 text-xs text-slate-500">Paste one block of code. It shows inside its own box on the post, so it never changes the rest of SmartSchool.</p>
                        <textarea name="body_html" rows="8" spellcheck="false" class="input mt-2 font-mono text-xs" placeholder="<style>.hero{…}</style><div class=&quot;hero&quot;>Family Day!</div><script>…</script>">{{ old('body_html') }}</textarea>
                    </details>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="audience" label="Who can see it" type="select" :options="\App\Models\Announcement::AUDIENCES" value="all" required />
                        <x-field name="section_id" label="Only this section (optional)" type="select" :options="$sections" />
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <x-field name="is_pinned" label="Pin to top" type="checkbox" :value="false" />
                        <x-field name="email" label="Also send by email" type="checkbox" :value="false" />
                    </div>
                    <button class="btn btn-primary min-h-11 w-full" :disabled="busy"><span x-text="busy ? 'Posting…' : 'Post'">Post</span></button>
                </form>
            </div>
        @endif

        @unless($canPost)
            @if($me->role === 'teacher')<p class="mb-4 rounded-xl bg-slate-100 p-3 text-sm text-slate-600 dark:bg-slate-800 dark:text-slate-300">School announcements come from the office. To announce something to your class, post it in that class's card (My classes → Create → Announcement).</p>@endif
        @endunless
        {{-- Feed --}}
        <div class="space-y-4">
            @forelse($items as $a)
                <article class="card relative p-0 {{ $a->readers->contains('id', $me->id) ? '' : 'ring-2 ring-brand-200 dark:ring-brand-500/30' }}">
                    <header class="flex items-start gap-3 px-4 pt-4">
                        @if($a->author?->avatar_path)<img src="{{ route('profile.photo', $a->author) }}" class="h-10 w-10 shrink-0 rounded-full object-cover" alt="">@else<span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700">{{ $initials($a->author?->name ?? 'School') }}</span>@endif
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-[15px] font-semibold text-slate-900">{{ $a->author?->name ?? 'School' }}</div>
                            <div class="flex flex-wrap items-center gap-1 text-xs text-slate-500">
                                @php $when = $a->published_at ?? $a->created_at; @endphp
                                <time datetime="{{ $when?->toIso8601String() }}" title="{{ $when?->format('M j, Y g:i A') }}">{{ $when?->diffForHumans() }}</time>
                                <span aria-hidden="true">·</span>
                                <x-icon name="{{ $a->audience === 'all' ? 'globe' : 'users' }}" class="h-3.5 w-3.5" />
                                <span>{{ \App\Models\Announcement::AUDIENCES[$a->audience] ?? $a->audience }}{{ $a->section ? ' · '.$a->section->name : '' }}</span>
                            </div>
                        </div>
                        @if($a->is_pinned)<span class="badge bg-brand-50 text-brand-700"><x-icon name="pin" class="h-3 w-3" /> Pinned</span>@endif
                        @if($me->isAdmin() || $a->author_id === $me->id)
                            <div class="relative" x-data="{ menu: false }">
                                <button type="button" x-on:click="menu = !menu" class="flex h-11 w-11 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100" aria-label="Post options" :aria-expanded="menu"><x-icon name="dots" class="h-5 w-5" /></button>
                                <div x-show="menu" x-cloak x-transition x-on:click.outside="menu = false" class="absolute right-0 z-10 mt-1 w-48 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-900/10">
                                    <form method="post" action="{{ route('announcements.destroy', $a) }}" x-on:submit="if (!confirm('Delete this announcement? This cannot be undone.')) $event.preventDefault()">
                                        @csrf @method('delete')
                                        <button class="flex min-h-11 w-full items-center gap-2 px-4 text-left text-sm text-rose-600 hover:bg-rose-50"><x-icon name="trash" class="h-4 w-4" /> Delete post</button>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </header>
                    <div class="px-4 pt-3 pb-4">
                        <h2 class="text-[17px] font-semibold text-slate-900">{{ $a->title }}</h2>
                        @if($a->event_at)
                            <p class="mt-2 inline-flex items-center gap-2 rounded-lg bg-brand-50 px-3 py-1.5 text-sm font-medium text-brand-800 dark:bg-brand-500/10 dark:text-brand-200"><x-icon name="calendar" class="h-4 w-4" /> {{ $a->event_at->format('l, F j, Y · g:i A') }}</p>
                        @endif
                        <p class="mt-2 text-[15px] leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $a->body }}</p>
                        @if($a->image_path)
                            <figure class="mt-3"><img src="{{ route('announcements.image', $a) }}" alt="{{ $a->image_caption ?: $a->title }}" class="w-full rounded-xl object-cover" loading="lazy">
                                @if($a->image_caption)<figcaption class="mt-1.5 text-sm text-slate-500">{{ $a->image_caption }}</figcaption>@endif</figure>
                        @endif
                        @if(filled($a->body_html))
                            <iframe src="{{ route('announcements.content', $a) }}" data-ss-frame="a{{ $a->id }}" sandbox="allow-scripts allow-popups allow-forms allow-modals" loading="lazy" class="mt-3 block h-48 w-full rounded-xl border border-slate-200 dark:border-slate-700" title="{{ $a->title }}"></iframe>
                        @endif
                    </div>
                    @php $read = $a->readers->contains('id', $me->id); $seen = $a->readers->where('id', '!=', $a->author_id); @endphp
                    <footer class="flex flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-2.5 dark:border-slate-800">
                        @if($seen->isNotEmpty())
                            <div class="flex items-center gap-2" x-data="{ who: false }">
                                <button type="button" class="flex -space-x-2" x-on:click="who = !who" title="Seen by">
                                    @foreach($seen->take(5) as $r)
                                        @if($r->avatar_path)<img src="{{ route('profile.photo', $r) }}" class="h-6 w-6 rounded-full object-cover ring-2 ring-white dark:ring-slate-900" alt="{{ $r->name }}">
                                        @else<span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-200 text-[9px] font-bold text-slate-700 ring-2 ring-white dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-900">{{ $initials($r->name) }}</span>@endif
                                    @endforeach
                                </button>
                                <span class="text-xs text-slate-500">Seen by {{ $seen->count() }}</span>
                                <div x-show="who" x-cloak x-on:click.outside="who = false" class="absolute z-20 mt-24 max-h-60 w-60 overflow-y-auto rounded-xl bg-white p-2 text-sm shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-900">
                                    @foreach($seen as $r)<p class="px-2 py-1">{{ $r->name }} <span class="text-xs text-slate-400">· {{ \Illuminate\Support\Carbon::parse($r->pivot->read_at)->diffForHumans() }}</span></p>@endforeach
                                </div>
                            </div>
                        @endif
                        @if($read)
                            <span class="ml-auto text-xs font-medium text-emerald-600">✓ Read</span>
                        @else
                            <form method="post" action="{{ route('announcements.read', $a) }}" class="ml-auto">@csrf<button class="rounded-full bg-brand-600 px-3 py-1 text-xs font-semibold text-white hover:bg-brand-700">Mark as read</button></form>
                        @endif
                    </footer>
                </article>
            @empty
                <div class="card p-0"><x-empty icon="megaphone" title="No announcements yet" :text="$canPost ? 'Posts you share will show up here for the people you choose.' : 'News from the school will show up here.'" /></div>
            @endforelse
            {{ $items->links() }}
        </div>
    </div>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        addEventListener('message', (e) => { const d = e.data; if (!d || typeof d.ssFrame !== 'string') return; const f = document.querySelector('iframe[data-ss-frame="' + d.ssFrame + '"]'); if (f && e.source === f.contentWindow) f.style.height = Math.min(Math.max(d.h, 80), 4000) + 'px' });
    </script>
</x-layouts.app>
