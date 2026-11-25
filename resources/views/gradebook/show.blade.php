@php $locked = in_array($submission?->status, ['submitted', 'approved']); $w = $cs->weights(); $labels = ['ww' => 'Quizzes', 'pt' => 'Projects', 'qa' => 'Exams / summative', 'at' => 'Attendance']; $isAdmin = auth()->user()->isAdmin(); @endphp
<x-layouts.app :title="$cs->subject->name.' · '.$cs->section->label()" :hideErrorSummary="true">
    @php $online = \App\Support\School::allows('classwork'); @endphp
    @if($online)
        <a href="{{ route('assignments.class', $cs) }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $cs->subject->name }} class card</a>
    @else
        <a href="{{ route('gradebook.index') }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />Classes</a>
    @endif
    <div class="mt-2 mb-4 flex flex-wrap items-center gap-3">
        <div class="flex-1">
            <h1 class="h1">{{ $cs->subject->name }}</h1>
            <p class="muted">{{ $cs->section->label() }} · {{ \App\Models\Setting::get('use_transmutation') === '1' ? 'transmuted grades' : 'plain weighted average' }}</p>
        </div>
        <div class="flex gap-1">@foreach(range(1, $terms) as $t)<a href="{{ route('gradebook.show', [$cs, 'term' => $t]) }}" class="btn btn-sm {{ $t === $term ? 'btn-primary' : '' }}">{{ \App\Support\School::term($t, $terms) }}</a>@endforeach</div>
    </div>
    @if($submission)
        <div class="mb-4 rounded-lg px-4 py-3 text-sm {{ $submission->status === 'returned' ? 'bg-amber-50 text-amber-900' : 'bg-blue-50 text-blue-900' }}">
            Term {{ $term }} status: <b>{{ ucfirst($submission->status) }}</b>{{ $submission->remarks ? ' – '.$submission->remarks : '' }}
            {{ $locked ? '· Editing is locked.' : '' }}
        </div>
    @endif

    {{-- How this class is graded --}}
    <div class="card mb-4" x-data="{ edit: {{ $errors->has('ww') || $errors->has('reason') ? 'true' : 'false' }}, w: @js(['ww' => old('ww', $w['ww']), 'pt' => old('pt', $w['pt']), 'qa' => old('qa', $w['qa']), 'at' => old('at', $w['at'])]) }">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="h2">How this class is graded</h2>
            @if($cs->hasCustomWeights())<span class="badge bg-violet-100 text-violet-700">Custom for this class</span>@endif
            <span class="flex-1"></span>
            @if($weightRequest?->status === 'pending')<span class="badge bg-amber-100 text-amber-800">Change waiting for admin approval</span>@endif
            <button @click="edit=!edit" class="btn btn-sm" x-text="edit ? 'Cancel' : '{{ $isAdmin ? 'Change weights' : 'Request a change' }}'"></button>
            @if($isAdmin && $cs->hasCustomWeights())<form method="post" action="{{ route('gradebook.weights.reset', $cs) }}">@csrf @method('delete')<button class="btn btn-sm">Use standard weights</button></form>@endif
        </div>
        <div class="mt-4 flex h-3 overflow-hidden rounded-full">
            @foreach(['ww' => 'bg-brand-500', 'pt' => 'bg-emerald-500', 'qa' => 'bg-amber-500', 'at' => 'bg-sky-500'] as $k => $c)<div class="{{ $c }}" style="width: {{ $w[$k] }}%"></div>@endforeach
        </div>
        <div class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
            @foreach(['ww' => 'bg-brand-500', 'pt' => 'bg-emerald-500', 'qa' => 'bg-amber-500', 'at' => 'bg-sky-500'] as $k => $c)<span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full {{ $c }}"></span>{{ $labels[$k] }} <b>{{ $w[$k] }}%</b></span>@endforeach
        </div>
        @if($w['at'] > 0)<p class="mt-2 text-xs text-slate-500">Attendance: present = full, late = half, absent = 0, counted within this grading period (class scans; daily attendance if none).</p>@endif
        @if($weightRequest && $weightRequest->status !== 'pending' && $weightRequest->reviewed_at?->gt(now()->subDays(14)))
            <p class="mt-3 text-sm {{ $weightRequest->status === 'approved' ? 'text-emerald-700' : 'text-rose-700' }}">Your last request was {{ $weightRequest->status }}{{ $weightRequest->review_note ? ': '.$weightRequest->review_note : '' }}.</p>
        @endif

        <form x-show="edit" x-cloak method="post" action="{{ route('gradebook.weights', $cs) }}" class="mt-5 border-t border-slate-100 pt-5">
            @csrf
            <div class="grid gap-4 sm:grid-cols-4">
                @foreach($labels as $k => $label)
                    <div><label class="label">{{ $label }}</label>
                        <div class="relative"><input type="number" min="0" max="100" name="{{ $k }}" x-model.number="w.{{ $k }}" class="input pr-8"><span class="absolute top-1/2 right-3 -translate-y-1/2 text-sm text-slate-400">%</span></div></div>
                @endforeach
            </div>
            <p class="mt-3 text-sm font-semibold" :class="(w.ww + w.pt + w.qa + w.at) === 100 ? 'text-emerald-700' : 'text-rose-600'">Total: <span x-text="w.ww + w.pt + w.qa + w.at"></span>% <span class="font-normal" x-show="(w.ww + w.pt + w.qa + w.at) !== 100">– must be exactly 100%</span></p>
            @error('ww')<p class="field-error">{{ $message }}</p>@enderror
            @unless($isAdmin)
                <div class="mt-4"><x-field name="reason" label="Why? (the admin will see this)" type="textarea" placeholder="e.g. Move 10% from quizzes to attendance because many students skip class." /></div>
            @endunless
            <div class="mt-4 flex items-center gap-3">
                <button class="btn btn-primary" :disabled="(w.ww + w.pt + w.qa + w.at) !== 100">{{ $isAdmin ? 'Apply to this class' : 'Send to admin for approval' }}</button>
                <span class="text-xs text-slate-500">{{ $isAdmin ? 'Applies right away and recomputes the grades.' : 'Nothing changes until the admin approves.' }}</span>
            </div>
        </form>
    </div>

    @if(! $locked && $online)
        {{-- Online mode: graded work comes from the class card, so it is also posted to students. --}}
        <div class="card mb-4 flex flex-wrap items-center gap-3">
            <div class="mr-auto">
                <p class="font-semibold">Add quizzes, projects and exams from the class card</p>
                <p class="text-sm text-slate-500">Create it there and choose where it counts – it appears here as a column and students see it. Scores you give when checking work are saved here automatically.</p>
            </div>
            <a href="{{ route('assignments.class', [$cs, 'create' => 1, 'term' => $term, 'component' => 'ww', 'type' => 'quiz']) }}" class="btn btn-primary">+ Create in class card</a>
        </div>
    @endif
    @if(! $locked && ! $online)
        <details class="card mb-4" {{ $assessments->isEmpty() ? 'open' : '' }}>
            <summary class="cursor-pointer font-semibold text-brand-700">+ Add quiz, task or exam</summary>
            <form method="post" action="{{ route('gradebook.assessments.store', $cs) }}" class="mt-3 grid gap-3 sm:grid-cols-5">
                @csrf <input type="hidden" name="term" value="{{ $term }}">
                <x-field name="component" label="Type" type="select" :options="\App\Models\Assessment::COMPONENTS" required />
                <x-field name="title" label="Title" placeholder="Quiz 1" required class="sm:col-span-2" />
                <x-field name="max_score" label="Highest possible score" type="number" step="0.5" required />
                <x-field name="given_on" label="Date" type="date" :value="now()->toDateString()" />
                <div class="sm:col-span-5"><button class="btn btn-primary">Add</button></div>
            </form>
        </details>
    @endif

    <form method="post" action="{{ route('gradebook.scores', $cs) }}" x-data="{ consult: false, who: '' }" x-on:keydown.escape.window="consult = false">
        @csrf <input type="hidden" name="term" value="{{ $term }}">
        {{-- Consultation (privacy) mode: show one student only, so they can see their own grade with the teacher. --}}
        <div class="mb-3 flex flex-wrap items-center gap-3">
            <button type="button" class="btn" x-show="!consult" x-on:click="consult = true; who = ''"><x-icon name="shield" class="h-4 w-4" /> Consultation mode</button>
            <div x-show="consult" x-cloak class="flex w-full flex-wrap items-center gap-3 rounded-2xl border-2 border-brand-500 bg-brand-50 p-3 dark:bg-brand-500/10">
                <x-icon name="shield" class="h-5 w-5 text-brand-700" />
                <span class="text-sm font-semibold text-brand-900 dark:text-brand-200">Consultation mode – only one student is shown</span>
                <select x-model="who" class="input h-10 w-auto min-w-56">
                    <option value="">Choose a student…</option>
                    @foreach($sheet as $sid => $row)<option value="{{ $sid }}">{{ $row['student']->full_name }}</option>@endforeach
                </select>
                <button type="button" class="btn btn-sm ml-auto" x-on:click="consult = false; who = ''">Exit</button>
            </div>
        </div>
        <p x-show="consult && !who" x-cloak class="card py-10 text-center text-slate-500">Choose the student you're talking with. Other students' grades stay hidden.</p>
        <div class="card overflow-x-auto p-0" x-show="!consult || who">
            <table class="table">
                <thead>
                    <tr>
                        <th class="sticky left-0 bg-slate-50">Learner</th>
                        @foreach($assessments as $a)
                            <th class="text-center">
                                <div class="text-[10px] text-slate-400">{{ \App\Models\Assessment::COMPONENTS[$a->component] }}</div>
                                {{ $a->title }}<div class="font-normal normal-case text-slate-400">/{{ rtrim(rtrim(number_format($a->max_score, 2), '0'), '.') }}</div>
                                @unless($locked)
                                    <button form="del-{{ $a->id }}" class="text-[10px] font-normal text-red-500 hover:underline">delete</button>
                                @endunless
                            </th>
                        @endforeach
                        <th class="text-center">Initial</th><th class="text-center">{{ \App\Support\School::periodName($terms) }} grade</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($sheet as $sid => $row)
                    </tbody><tbody x-data="{ open: false }" x-show="!consult || who === '{{ $sid }}'" x-effect="if (consult && who === '{{ $sid }}') open = true" class="border-b border-slate-100 dark:border-slate-800">
                    <tr>
                        <td class="sticky left-0 whitespace-nowrap bg-white dark:bg-slate-900"><button type="button" class="flex items-center gap-1.5 text-left hover:text-brand-600" x-on:click="open = !open" :aria-expanded="open" title="Show how this grade is computed"><span class="text-slate-400 transition" :class="open && 'rotate-90'"><x-icon name="chevron" class="h-3.5 w-3.5" /></span>{{ $row['student']->full_name }}</button></td>
                        @foreach($assessments as $a)
                            @php $v = old("scores.{$a->id}.{$sid}", $scores[$sid][$a->id]->score ?? null); @endphp
                            <td class="p-1 text-center">
                                <input name="scores[{{ $a->id }}][{{ $sid }}]" value="{{ $v !== null ? rtrim(rtrim(number_format((float) $v, 2), '0'), '.') : '' }}" inputmode="decimal" @disabled($locked)
                                       class="w-16 rounded border px-1 py-1 text-center text-sm @error("scores.{$a->id}.{$sid}") border-red-500 bg-red-50 @else border-slate-200 @enderror">
                            </td>
                        @endforeach
                        <td class="text-center text-slate-500">{{ $row['initial'] ?? '' }}</td>
                        <td class="text-center font-bold {{ $row['quarterly'] !== null && $row['quarterly'] < (float) \App\Models\Setting::get('passing_grade') ? 'text-red-600' : '' }}"><button type="button" class="hover:underline" x-on:click="open = !open">{{ $row['quarterly'] ?? '' }}</button></td>
                    </tr>
                    <tr x-show="open" x-cloak>
                        <td colspan="99" class="bg-slate-50 px-4 py-4 dark:bg-white/5">
                            @php
                                $used = collect($w)->filter(fn ($wt, $c) => $wt > 0 && (($row['totals'][$c][1] ?? 0) > 0))->sum();
                                $compLabel = ['ww' => 'Quizzes / written work', 'pt' => 'Projects / performance', 'qa' => 'Exams / summative', 'at' => 'Attendance'];
                                $f = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
                            @endphp
                            <p class="mb-3 text-sm font-semibold">How {{ $row['student']->first_name }}'s grade is computed</p>
                            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_260px]">
                                <table class="w-full text-sm">
                                    <thead class="text-xs text-slate-500"><tr><th class="py-1 text-left font-medium">Part</th><th class="text-right font-medium">Score</th><th class="text-right font-medium">%</th><th class="text-right font-medium">Weight</th><th class="text-right font-medium">Points</th></tr></thead>
                                    <tbody>
                                    @foreach($w as $c => $wt)
                                        @continue($wt <= 0)
                                        @php [$sc, $mx] = $row['totals'][$c] ?? [0, 0]; $pct = $mx > 0 ? $sc / $mx * 100 : null; $eff = $used > 0 ? $wt * 100 / $used : 0; @endphp
                                        <tr class="border-t border-slate-200 dark:border-slate-700 {{ $pct === null ? 'text-slate-400' : '' }}">
                                            <td class="py-1.5">{{ $compLabel[$c] ?? $c }}</td>
                                            <td class="text-right">{{ $mx > 0 ? $f($sc).' / '.$f($mx) : 'none yet' }}</td>
                                            <td class="text-right">{{ $pct !== null ? $f($pct).'%' : '—' }}</td>
                                            <td class="text-right">{{ $wt }}%@if($pct !== null && $used < 100)<span class="text-xs text-slate-400"> → {{ $f($eff) }}%</span>@endif</td>
                                            <td class="text-right font-semibold">{{ $pct !== null ? $f($pct * $eff / 100) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr class="border-t-2 border-slate-300 font-bold dark:border-slate-600"><td class="py-1.5" colspan="4">Initial grade</td><td class="text-right">{{ $row['initial'] ?? '—' }}</td></tr>
                                    </tbody>
                                </table>
                                <div class="rounded-xl bg-white p-4 text-sm shadow-sm dark:bg-slate-900">
                                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \App\Support\School::periodName($terms) }} grade</p>
                                    <p class="text-4xl font-extrabold {{ $row['quarterly'] !== null && $row['quarterly'] < (float) \App\Models\Setting::get('passing_grade') ? 'text-red-600' : 'text-brand-600' }}">{{ $row['quarterly'] ?? '—' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ \App\Models\Setting::get('use_transmutation') === '1' ? 'Initial grade '.($row['initial'] ?? '—').' changed with the DepEd transmutation table.' : 'Initial grade '.($row['initial'] ?? '—').' rounded to a whole number.' }}</p>
                                    @if($used > 0 && $used < 100)<p class="mt-2 text-xs text-amber-700">Some parts have no scores yet, so the others count proportionally more until they do.</p>@endif
                                    <p class="mt-2 text-xs text-slate-500">{{ \App\Services\Grading::descriptor($row['quarterly']) }}</p>
                                </div>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="99" class="py-8 text-center text-slate-500">No students in this section yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @unless($locked)
            <div class="mt-4 flex flex-wrap gap-2">
                @if($assessments->isNotEmpty())<button class="btn btn-primary">Save scores</button>@endif
            </div>
        @endunless
    </form>
    @foreach($assessments as $a)
        <form id="del-{{ $a->id }}" method="post" action="{{ route('gradebook.assessments.destroy', $a) }}" x-data x-on:submit="if (!confirm('Delete {{ $a->title }} and all its scores?')) $event.preventDefault()">@csrf @method('delete')</form>
    @endforeach
    @if(! $locked && $assessments->isNotEmpty())
        <form x-data method="post" action="{{ route('gradebook.submit', $cs) }}" class="mt-6 border-t pt-4" x-on:submit="if (!confirm('Submit term {{ $term }} grades? You cannot edit them unless the registrar returns them.')) $event.preventDefault()">
            @csrf <input type="hidden" name="term" value="{{ $term }}">
            <button class="btn">Submit term {{ $term }} grades for approval</button>
            <span class="muted ml-2">Every score change is recorded in the audit log.</span>
        </form>
    @endif
</x-layouts.app>
