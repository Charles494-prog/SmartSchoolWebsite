{{-- One post on a class card. Vars: $p (Assignment), $teacher (bool), $sub (?Submission, student view), $students (count, teacher view) --}}
@php
    [$tLabel, $tIcon, $tClass, $graded] = \App\Support\Classwork::TYPES[$p->type] ?? \App\Support\Classwork::TYPES['assignment'];
    $hasDesign = filled($p->body_html) || filled($p->body_js);
@endphp
<article x-data="{ open: false }" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" x-show="filter === 'all' || filter === '{{ $p->type }}' || (filter === 'work' && {{ $graded ? 'true' : 'false' }}) || (filter === 'todo' && {{ ! $teacher && $graded && ! $sub ? 'true' : 'false' }})">
    <button type="button" class="flex w-full items-center gap-4 p-4 text-left" x-on:click="open = !open{{ ! $teacher && $p->track_reads && auth()->user()->role === 'student' ? "; if (open && !\$el.dataset.read) { \$el.dataset.read = 1; fetch('".route('posts.read', $p)."', { method: 'POST', headers: { 'X-CSRF-TOKEN': '".csrf_token()."' } }) }" : '' }}" :aria-expanded="open">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full {{ $tClass }}"><x-icon :name="$tIcon" class="h-5 w-5" /></span>
        <span class="min-w-0 flex-1">
            <span class="flex flex-wrap items-center gap-2">
                <span class="truncate font-semibold text-slate-900 dark:text-white">{{ $p->title }}</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $tClass }}">{{ $tLabel }}</span>
            </span>
            <span class="block text-sm text-slate-500">
                @if($p->due_at) Due {{ $p->due_at->format('D, M d · g:i A') }} · {{ rtrim(rtrim(number_format((float) $p->max_score, 2), '0'), '.') }} pts · @endif
                {{ $graded ? \App\Support\Classwork::countsToward($p) : 'Posted '.$p->created_at->format('M d') }}
            </span>
        </span>
        @if($teacher && $graded)
            @php $toCheck = $p->submissions_count - $p->graded_count; @endphp
            <span class="hidden text-right text-sm sm:block">
                <span class="block font-semibold text-slate-900 dark:text-white">{{ $p->submissions_count }}/{{ $students }} turned in</span>
                <span class="block {{ $toCheck ? 'font-medium text-amber-600' : 'text-slate-500' }}">{{ $toCheck ? $toCheck.' to check' : 'All checked' }}</span>
            </span>
        @elseif(! $teacher && $graded)
            @if($sub?->score !== null)<span class="rounded-full bg-emerald-100 px-3 py-1 text-sm font-bold text-emerald-700">{{ rtrim(rtrim(number_format($sub->score, 2), '0'), '.') }}/{{ rtrim(rtrim(number_format((float) $p->max_score, 2), '0'), '.') }}</span>
            @elseif($sub)<span class="rounded-full bg-blue-100 px-3 py-1 text-sm font-semibold text-blue-700">Turned in</span>
            @elseif($p->due_at?->isPast())<span class="rounded-full bg-rose-100 px-3 py-1 text-sm font-semibold text-rose-700">Missing</span>
            @else<span class="rounded-full bg-amber-100 px-3 py-1 text-sm font-semibold text-amber-800">To do</span>@endif
        @endif
        <span class="shrink-0 text-slate-400 transition" :class="open && 'rotate-90'"><x-icon name="chevron" class="h-4 w-4" /></span>
    </button>

    <div x-show="open" x-cloak class="space-y-4 border-t border-slate-100 p-5 dark:border-slate-800">
        @if($p->instructions)<p class="whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $p->instructions }}</p>@endif
        @if($hasDesign)
            <iframe src="{{ route('assignments.content', $p) }}" data-ss-frame="{{ $p->id }}" sandbox="allow-scripts allow-popups allow-forms allow-modals" loading="lazy"
                    class="block h-48 w-full rounded-xl border border-slate-200 bg-white dark:border-slate-700" title="{{ $p->title }} – teacher's content"></iframe>
        @endif
        @if($p->attachment_path)
            @php $ext = strtolower(pathinfo($p->attachment_path, PATHINFO_EXTENSION)); $src = route('assignments.attachment', [$p, 'inline' => 1]); @endphp
            @if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
                <a href="{{ $src }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-white/5"><img src="{{ $src }}" alt="Picture attached to {{ $p->title }}" loading="lazy" class="mx-auto max-h-[28rem] w-auto object-contain"></a>
            @elseif(in_array($ext, ['mp4', 'webm', 'mov'], true))
                <video src="{{ $src }}" controls preload="metadata" class="max-h-[28rem] w-full rounded-xl border border-slate-200 bg-black dark:border-slate-700"><track kind="captions"></video>
            @else
                <a class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-white/5" href="{{ route('assignments.attachment', $p) }}">
                    <x-icon name="clip" class="h-4 w-4" /> {{ basename($p->attachment_path) }}
                </a>
            @endif
        @endif

        @if($teacher)
            <div class="flex flex-wrap gap-2">
                @if($p->type === 'quiz')
                    <a href="{{ route('quiz.edit', $p) }}" class="btn btn-primary btn-sm">Edit questions</a>
                    <a href="{{ route('quiz.results', $p) }}" class="btn btn-sm">Quiz results</a>
                @elseif($graded)<a href="{{ route('assignments.show', $p) }}" class="btn btn-primary btn-sm">Check work</a>@endif
                <a href="{{ route('assignments.show', [$p, 'edit' => 1]) }}" class="btn btn-sm">Edit</a>
                @if($p->assessment_id)<a href="{{ route('gradebook.show', [$p->class_subject_id, 'term' => $p->term]) }}" class="btn btn-sm">See in gradebook</a>@endif
                <form method="post" action="{{ route('assignments.destroy', $p) }}" x-on:submit="if (!confirm(@js('Delete this post?'.($p->assessment_id ? ' Its gradebook column and scores are deleted too.' : '')))) $event.preventDefault()">@csrf @method('DELETE')<button class="btn btn-sm text-rose-600">Delete</button></form>
            </div>
        @elseif($graded)
            @if($sub?->feedback)<p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"><b>Teacher feedback:</b> {{ $sub->feedback }}</p>@endif
            @if($p->type === 'quiz')
                @if(auth()->user()->role === 'student')<a href="{{ route('quiz.take', $p) }}" class="btn btn-primary">{{ $sub ? 'See my quiz' : 'Take quiz' }}</a>@endif
            @elseif(auth()->user()->role === 'student' && $sub?->score === null)
                <form method="post" action="{{ route('portal.submit', $p) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                    @csrf
                    <x-field name="body" label="Your answer / notes" type="textarea" :value="$sub?->body" />
                    <x-field name="file" label="Attach file (max 10 MB)" type="file" />
                    <button class="btn btn-primary">{{ $sub ? 'Turn in again' : 'Turn in' }}</button>
                </form>
            @endif
        @endif

        {{-- Seen by + comments --}}
        @php
            $mutedIds ??= []; $muted ??= false;
            $me = auth()->user();
            $canComment = $teacher || ($me->role === 'student' && $p->allow_comments && ! $muted);
        @endphp
        @if($teacher)
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-xl bg-slate-50 px-3 py-2 text-sm dark:bg-white/5" x-data="{ seen: false }">
                <form method="post" action="{{ route('posts.settings', $p) }}" class="flex flex-wrap items-center gap-x-5 gap-y-2" x-data x-on:change="$el.requestSubmit()">@csrf
                    <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="allow_comments" value="1" class="h-4 w-4 rounded" @checked($p->allow_comments)> Students can comment</label>
                    <label class="flex min-h-11 items-center gap-2"><input type="checkbox" name="track_reads" value="1" class="h-4 w-4 rounded" @checked($p->track_reads)> Show who has seen it</label>
                </form>
                @if($p->track_reads)
                    <button type="button" class="ml-auto inline-flex min-h-11 items-center gap-1.5 font-medium text-brand-700 dark:text-brand-300" x-on:click="seen = !seen" :aria-expanded="seen"><x-icon name="eye" class="h-4 w-4" /> Seen by {{ $p->reads->count() }} of {{ $students }}</button>
                    <ul x-show="seen" x-cloak class="flex w-full flex-wrap gap-1.5">
                        @forelse($p->reads->sortBy('read_at') as $r)
                            <li class="rounded-full bg-white px-2.5 py-1 text-xs ring-1 ring-slate-200 dark:bg-white/10 dark:ring-white/10" title="{{ $r->read_at->format('M d, g:i A') }}">{{ $r->user?->name }}</li>
                        @empty
                            <li class="text-xs text-slate-500">No one has opened it yet.</li>
                        @endforelse
                    </ul>
                @endif
            </div>
        @endif

        @if($p->allow_comments || $p->comments->isNotEmpty() || $teacher)
            <section class="space-y-3 border-t border-slate-100 pt-4 dark:border-slate-800" aria-label="Comments">
                <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $p->comments->count() }} {{ str('comment')->plural($p->comments->count()) }}</h4>
                @foreach($p->comments as $cm)
                    @php $isMuted = in_array($cm->user_id, $mutedIds, true); @endphp
                    <div class="flex gap-3">
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600 dark:bg-white/10 dark:text-slate-200">{{ collect(explode(' ', (string) $cm->user?->name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="rounded-2xl bg-slate-100 px-3 py-2 dark:bg-white/5">
                                <p class="text-xs font-semibold text-slate-900 dark:text-white">{{ $cm->user?->name }}@if($cm->user?->role === 'teacher' || $cm->user?->role === 'admin') <span class="ml-1 rounded bg-brand-100 px-1 text-[10px] text-brand-800 dark:bg-brand-500/20 dark:text-brand-200">Teacher</span>@endif @if($isMuted)<span class="ml-1 text-[10px] font-normal text-rose-600">can't comment</span>@endif</p>
                                <p class="text-sm whitespace-pre-line text-slate-800 dark:text-slate-200">{{ $cm->body }}</p>
                            </div>
                            <div class="mt-0.5 flex flex-wrap items-center gap-3 px-2 text-xs text-slate-500">
                                <span>{{ $cm->created_at->diffForHumans() }}</span>
                                @if($teacher || $cm->user_id === $me->id)
                                    <form method="post" action="{{ route('posts.comments.destroy', $cm) }}" x-data x-on:submit="if (!confirm('Delete this comment?')) $event.preventDefault()">@csrf @method('delete')<button class="min-h-8 font-medium hover:text-rose-600">Delete</button></form>
                                @endif
                                @if($teacher && $cm->user?->role === 'student')
                                    <form method="post" action="{{ route('posts.mute', [$p->class_subject_id, $cm->user_id]) }}" x-data x-on:submit="if (!confirm(@js($isMuted ? 'Let '.$cm->user->name.' comment again in this class?' : 'Stop '.$cm->user->name.' from commenting in this class?'))) $event.preventDefault()">@csrf<button class="min-h-8 font-medium hover:text-rose-600">{{ $isMuted ? 'Allow commenting' : 'Stop commenting' }}</button></form>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
                @if($canComment)
                    <form method="post" action="{{ route('posts.comments.store', $p) }}" class="flex items-end gap-2" x-data="{ body: '' }" x-on:ajax-done.window="body = ''">@csrf
                        <textarea name="body" x-model="body" rows="1" maxlength="1000" class="input min-h-11 flex-1 resize-y" placeholder="{{ $teacher ? 'Reply to the class…' : 'Add a class comment…' }}" aria-label="Write a comment" required></textarea>
                        <button class="btn btn-primary shrink-0" :disabled="!body.trim()">Post</button>
                    </form>
                @elseif($me->role === 'student')
                    <p class="text-xs text-slate-500">{{ $muted ? 'Your teacher turned off commenting for you in this class.' : 'Comments are turned off for this post.' }}</p>
                @endif
            </section>
        @endif
    </div>
</article>
@once
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        // Grow each sandboxed teacher-content frame to fit what's inside it.
        addEventListener('message', (e) => {
            const d = e.data; if (!d || typeof d.ssFrame !== 'number') return;
            const f = document.querySelector('iframe[data-ss-frame="' + d.ssFrame + '"]');
            if (f && e.source === f.contentWindow) f.style.height = Math.min(Math.max(d.h, 80), 4000) + 'px';
        });
    </script>
@endonce
