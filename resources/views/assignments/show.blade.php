<x-layouts.app :title="$a->title">
    @php
        $CW = \App\Support\Classwork::class;
        [$tLabel, $tIcon, $tClass, $graded] = $CW::TYPES[$a->type] ?? $CW::TYPES['assignment'];
        $f = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        $terms = $a->classSubject->section->gradeLevel->terms ?: 4;
        $turned = $subs->count();
        $checked = $subs->whereNotNull('score')->count();
        $avg = $subs->whereNotNull('score')->avg('score');
        $status = fn ($s) => ! ($sub = $subs[$s->id] ?? null) ? 'missing' : ($sub->score !== null ? 'checked' : 'tocheck');
    @endphp
    <div x-data="{ edit: {{ $errors->any() || request()->has('edit') ? 'true' : 'false' }}, filter: 'all' }">
        <a href="{{ route('assignments.class', [$a->class_subject_id, 'tab' => 'work']) }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->name }}</a>

        {{-- Header --}}
        <div class="mt-3 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-wrap items-start gap-4">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $tClass }}"><x-icon :name="$tIcon" class="h-6 w-6" /></span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ $tLabel }}</p>
                    <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $a->title }}</h1>
                    <div class="mt-2 flex flex-wrap gap-2 text-sm">
                        @if($a->due_at)<span class="rounded-lg bg-slate-100 px-2.5 py-1 dark:bg-slate-800">Due {{ $a->due_at->format('D, M d · g:i A') }}</span>@endif
                        @if($graded)<span class="rounded-lg bg-slate-100 px-2.5 py-1 dark:bg-slate-800">{{ $f($a->max_score) }} points</span>
                        <span class="rounded-lg px-2.5 py-1 {{ $a->component ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}">{{ $CW::countsToward($a) }}</span>@endif
                    </div>
                </div>
                <div class="flex gap-2">
                    @if($a->type === 'quiz')<a href="{{ route('quiz.edit', $a) }}" class="btn">Edit questions</a>@endif
                    <button type="button" class="btn" x-on:click="edit = !edit" x-text="edit ? 'Close' : 'Edit'">Edit</button>
                    <form method="post" action="{{ route('assignments.destroy', $a) }}" x-on:submit="if (!confirm(@js('Delete “'.$a->title.'”?'.($a->assessment_id ? ' Its gradebook column and scores are deleted too.' : '')))) $event.preventDefault()">@csrf @method('DELETE')
                        <button class="btn text-rose-600">Delete</button>
                    </form>
                </div>
            </div>
            @if($a->instructions)<p class="mt-4 whitespace-pre-line text-slate-700 dark:text-slate-300">{{ $a->instructions }}</p>@endif
            @if($a->attachment_path)<a class="mt-3 inline-flex items-center gap-2 rounded-xl border border-slate-200 px-3 py-2 text-sm hover:bg-slate-50 dark:border-slate-700" href="{{ route('assignments.attachment', $a) }}"><x-icon name="clip" class="h-4 w-4" /> {{ basename($a->attachment_path) }}</a>@endif
            @if(filled($a->body_html))<iframe src="{{ route('assignments.content', $a) }}" data-ss-frame="{{ $a->id }}" sandbox="allow-scripts allow-popups allow-forms allow-modals" class="mt-4 block h-48 w-full rounded-xl border border-slate-200 dark:border-slate-700" title="Teacher's content"></iframe>@endif

            {{-- Edit --}}
            <form x-show="edit" x-cloak method="post" action="{{ route('assignments.update', $a) }}" enctype="multipart/form-data" class="mt-6 space-y-4 border-t border-slate-100 pt-6 dark:border-slate-800" x-data="{ counts: @js((string) old('component', $a->component ?? '')) }">
                @csrf @method('PUT')
                <x-field name="title" label="Title" :value="$a->title" required />
                <x-field name="instructions" label="Message / instructions" type="textarea" :value="$a->instructions" />
                @if($graded)
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-field name="due_at" label="Due" type="datetime-local" :value="$a->due_at?->format('Y-m-d\TH:i')" />
                        <x-field name="max_score" label="Points" type="number" :value="$f($a->max_score)" />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block"><span class="label">Counts toward</span>
                            <select name="component" class="input" x-model="counts">
                                <option value="">Not included in the grade</option>
                                @foreach($CW::COMPONENTS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select></label>
                        <label class="block" x-show="counts"><span class="label">Grading period</span>
                            <select name="term" class="input">@foreach(range(1, $terms) as $t)<option value="{{ $t }}" @selected((int) old('term', $a->term) === $t)>{{ \App\Support\School::periodName($terms) }} {{ $t }}{{ $a->classSubject->isLocked($t) ? ' – submitted' : '' }}</option>@endforeach</select></label>
                    </div>
                @endif
                <label class="block"><span class="label">Your code (HTML / CSS / JS) – optional</span><textarea name="body_html" rows="6" spellcheck="false" class="input font-mono text-xs">{{ old('body_html', $a->body_html) }}</textarea></label>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <x-field name="attachment" label="Replace attachment" type="file" />
                    @if($a->attachment_path)<label class="flex items-center gap-2"><input type="hidden" name="remove_attachment" value="0"><input type="checkbox" name="remove_attachment" value="1"> Remove current file</label>@endif
                </div>
                <div class="flex gap-2"><button class="btn btn-primary">Save changes</button><button type="button" class="btn" x-on:click="edit = false">Cancel</button></div>
            </form>
        </div>

        @if($graded)
            {{-- Summary --}}
            <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="text-2xl font-bold tabular-nums">{{ $turned }}<span class="text-base font-normal text-slate-400">/{{ $students->count() }}</span></div><div class="text-sm text-slate-500">turned in</div></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="text-2xl font-bold tabular-nums text-amber-600">{{ $turned - $checked }}</div><div class="text-sm text-slate-500">to check</div></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="text-2xl font-bold tabular-nums">{{ $students->count() - $turned }}</div><div class="text-sm text-slate-500">missing</div></div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900"><div class="text-2xl font-bold tabular-nums">{{ $avg !== null ? $f($avg) : '—' }}</div><div class="text-sm text-slate-500">average score</div></div>
            </div>

            {{-- Students --}}
            <div class="mt-4 rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="flex flex-wrap gap-2 border-b border-slate-100 p-3 dark:border-slate-800">
                    @foreach(['all' => 'Everyone', 'tocheck' => 'To check', 'checked' => 'Checked', 'missing' => 'Missing'] as $k => $label)
                        <button type="button" class="rounded-full px-3 py-1 text-sm font-medium" :class="filter === '{{ $k }}' ? 'bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-white/5'" x-on:click="filter = '{{ $k }}'">{{ $label }}</button>
                    @endforeach
                </div>
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach($students as $s)
                        @php $sub = $subs[$s->id] ?? null; $st = $status($s); $late = $sub && $a->due_at && $sub->submitted_at > $a->due_at; @endphp
                        <li class="flex flex-wrap items-center gap-4 px-5 py-4" x-show="filter === 'all' || filter === '{{ $st }}'">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ strtoupper(substr($s->first_name, 0, 1).substr($s->last_name, 0, 1)) }}</span>
                            <div class="min-w-0 flex-1 basis-48">
                                <p class="font-medium text-slate-900 dark:text-white">{{ $s->full_name }}</p>
                                <p class="text-xs">
                                    @if($st === 'missing')<span class="text-slate-400">Not turned in</span>
                                    @else<span class="{{ $late ? 'text-amber-600' : 'text-emerald-600' }}">{{ $late ? 'Late' : 'On time' }}</span> <span class="text-slate-400">· {{ $sub->submitted_at->format('M d, g:i A') }}</span>@endif
                                </p>
                                @if($sub?->body || $sub?->file_path)
                                    <p class="mt-1 line-clamp-2 text-sm text-slate-600 dark:text-slate-300">{{ $sub->body }} @if($sub->file_path)<a class="link whitespace-nowrap" href="{{ route('assignments.file', $sub) }}"><x-icon name="clip" class="inline h-3.5 w-3.5" /> file</a>@endif</p>
                                @endif
                            </div>
                            @if($a->type === 'quiz')
                                <span class="text-sm {{ $sub?->score !== null ? 'font-bold' : 'text-slate-400' }}">{{ $sub?->score !== null ? $f($sub->score).' / '.$f($a->max_score) : ($sub ? 'Checking essays' : '—') }}</span>
                            @else
                                <form method="post" action="{{ $sub ? route('assignments.grade', $sub) : route('assignments.grade-student', [$a, $s]) }}" class="flex flex-wrap items-center gap-2">@csrf
                                    <span class="flex items-center gap-1"><input name="score" value="{{ $sub?->score !== null ? $f($sub->score) : '' }}" inputmode="decimal" class="input h-10 w-20 text-center {{ $st === 'tocheck' ? 'border-amber-400' : '' }}" placeholder="—" aria-label="Score for {{ $s->full_name }}"><span class="text-sm text-slate-400">/ {{ $f($a->max_score) }}</span></span>
                                    <input name="feedback" value="{{ $sub?->feedback }}" class="input h-10 w-56" placeholder="Feedback (optional)">
                                    <button class="btn h-10">{{ $sub?->score !== null ? 'Update' : 'Save' }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
            @if($a->type === 'quiz')<p class="mt-3 text-sm text-slate-500">Quiz scores are checked automatically. Open <a class="link" href="{{ route('quiz.results', $a) }}">Quiz results</a> to check essays or change points.</p>@endif
        @endif
    </div>
</x-layouts.app>
