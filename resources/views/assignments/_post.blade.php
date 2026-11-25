{{-- One post on a class card. Vars: $p (Assignment), $teacher (bool), $sub (?Submission, student view), $students (count, teacher view) --}}
@php
    [$tLabel, $tIcon, $tClass, $graded] = \App\Support\Classwork::TYPES[$p->type] ?? \App\Support\Classwork::TYPES['assignment'];
    $hasDesign = filled($p->body_html) || filled($p->body_js);
@endphp
<article x-data="{ open: false }" class="rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900" x-show="filter === 'all' || filter === '{{ $p->type }}' || (filter === 'work' && {{ $graded ? 'true' : 'false' }})">
    <button type="button" class="flex w-full items-center gap-4 p-4 text-left" x-on:click="open = !open" :aria-expanded="open">
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
            <a class="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm font-medium hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-white/5" href="{{ route('assignments.attachment', $p) }}">
                <x-icon name="clip" class="h-4 w-4" /> {{ basename($p->attachment_path) }}
            </a>
        @endif

        @if($teacher)
            <div class="flex flex-wrap gap-2">
                @if($graded)<a href="{{ route('assignments.show', $p) }}" class="btn btn-primary btn-sm">Check work</a>@endif
                @if($p->assessment_id)<a href="{{ route('gradebook.show', [$p->class_subject_id, 'term' => $p->term]) }}" class="btn btn-sm">See in gradebook</a>@endif
                <form method="post" action="{{ route('assignments.destroy', $p) }}" onsubmit="return confirm('Delete this post?{{ $p->assessment_id ? ' Its gradebook column and scores are deleted too.' : '' }}')">@csrf @method('DELETE')<button class="btn btn-sm text-rose-600">Delete</button></form>
            </div>
        @elseif($graded)
            @if($sub?->feedback)<p class="rounded-xl bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-500/10 dark:text-emerald-200"><b>Teacher feedback:</b> {{ $sub->feedback }}</p>@endif
            @if(auth()->user()->role === 'student' && $sub?->score === null)
                <form method="post" action="{{ route('portal.submit', $p) }}" enctype="multipart/form-data" class="space-y-3 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                    @csrf
                    <x-field name="body" label="Your answer / notes" type="textarea" :value="$sub?->body" />
                    <x-field name="file" label="Attach file (max 10 MB)" type="file" />
                    <button class="btn btn-primary">{{ $sub ? 'Turn in again' : 'Turn in' }}</button>
                </form>
            @endif
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
