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

                <form x-show="open" x-cloak x-transition.opacity.duration.250ms method="post" action="{{ route('announcements.store') }}" class="mt-4 space-y-3 border-t border-slate-100 pt-4"
                      x-data="{ busy: false }" x-on:submit="busy = true">
                    @csrf
                    <div x-ref="title"><x-field name="title" label="Title" required /></div>
                    <x-field name="body" label="Message" type="textarea" required />
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="audience" label="Who can see it" type="select" :options="\App\Models\Announcement::AUDIENCES" :value="$me->role === 'teacher' ? 'parents' : 'all'" required />
                        <x-field name="section_id" label="{{ $me->role === 'teacher' ? 'Section' : 'Only this section (optional)' }}" type="select" :options="$sections" :required="$me->role === 'teacher'" />
                    </div>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                        <x-field name="is_pinned" label="Pin to top" type="checkbox" :value="false" />
                        <x-field name="email" label="Also send by email" type="checkbox" :value="false" />
                    </div>
                    <button class="btn btn-primary min-h-11 w-full" :disabled="busy"><span x-text="busy ? 'Posting…' : 'Post'">Post</span></button>
                </form>
            </div>
        @endif

        {{-- Feed --}}
        <div class="space-y-4">
            @forelse($items as $a)
                <article class="card p-0">
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
                        <p class="mt-1 text-[15px] leading-relaxed whitespace-pre-line text-slate-700">{{ $a->body }}</p>
                    </div>
                </article>
            @empty
                <div class="card p-0"><x-empty icon="megaphone" title="No announcements yet" :text="$canPost ? 'Posts you share will show up here for the people you choose.' : 'News from the school will show up here.'" /></div>
            @endforelse
            {{ $items->links() }}
        </div>
    </div>
</x-layouts.app>
