<x-layouts.app :title="'Quiz · '.$a->title">
    <a href="{{ route('assignments.class', [$a->class_subject_id, 'tab' => 'work']) }}" class="link text-sm"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $a->classSubject->subject->name }} · {{ $a->classSubject->section->name }}</a>

    <form method="post" action="{{ route('quiz.save', $a) }}" x-data="quizBuilder(@js($questions->map(fn ($q) => ['type' => $q->type, 'title' => $q->title, 'text' => $q->text, 'points' => (float) $q->points, 'data' => $q->data, 'feedback' => $q->feedback])->values()))"
          x-on:submit="$refs.json.value = JSON.stringify(questions)" class="mt-3">
        @csrf @method('PUT')
        <input type="hidden" name="questions" x-ref="json">

        {{-- Header --}}
        <div class="mb-5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="h-1.5 bg-brand-600"></div>
            <div class="flex flex-wrap items-end gap-4 p-6">
                <div class="mr-auto">
                    <p class="text-xs font-semibold uppercase tracking-wider text-brand-600">Quiz builder</p>
                    <h1 class="text-2xl font-bold">{{ $a->title }}</h1>
                    <p class="text-sm text-slate-500"><span x-text="questions.length"></span> questions · <span x-text="total()"></span> quiz points → scaled to {{ rtrim(rtrim(number_format((float) $a->max_score, 2), '0'), '.') }} pts @if($a->due_at)· due {{ $a->due_at->format('M d, g:i A') }}@endif</p>
                </div>
                <a href="{{ route('quiz.results', $a) }}" class="btn">Results @if($attemptCount)<span class="rounded-full bg-brand-100 px-2 text-xs text-brand-700">{{ $attemptCount }}</span>@endif</a>
                <a href="{{ route('quiz.export', $a) }}" class="btn">Export GIFT</a>
                <button class="btn btn-primary">Save quiz</button>
            </div>
            @if($attemptCount)<p class="border-t border-amber-200 bg-amber-50 px-6 py-2 text-sm text-amber-900">Students already took this quiz. Editing questions changes how new attempts are checked; existing scores stay.</p>@endif
        </div>

        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
            {{-- Questions --}}
            <div class="space-y-4">
                <template x-for="(q, i) in questions" :key="q._k">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" :class="open === q._k && 'ring-2 ring-brand-500'" x-on:click="open = q._k">
                        <div class="flex flex-wrap items-start gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-100 text-sm font-bold text-brand-700" x-text="i + 1"></span>
                            <textarea x-model="q.text" rows="2" class="input min-w-0 flex-1 text-base font-medium" placeholder="Question"></textarea>
                            <select x-model="q.type" x-on:change="retype(q)" class="input w-full sm:w-56">
                                @foreach(\App\Models\QuizQuestion::TYPES as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach
                            </select>
                        </div>

                        <div class="mt-4 pl-11">
                            {{-- Multiple choice / checkboxes --}}
                            <template x-if="q.type === 'mc' || q.type === 'multi'">
                                <div class="space-y-2">
                                    <template x-for="(o, j) in q.data.options" :key="j">
                                        <div class="flex items-center gap-2">
                                            <button type="button" class="flex h-6 w-6 shrink-0 items-center justify-center border-2" :class="[q.type === 'mc' ? 'rounded-full' : 'rounded-md', o.weight > 0 ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-slate-300']" x-on:click="toggleCorrect(q, j)" :title="o.weight > 0 ? 'Correct' : 'Mark as correct'">
                                                <svg x-show="o.weight > 0" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12l5 5L20 7"/></svg>
                                            </button>
                                            <input x-model="o.text" class="input h-10 flex-1" :placeholder="'Option ' + (j + 1)">
                                            <input x-show="q.type === 'multi' && !q.data.all_or_nothing" x-model.number="o.weight" type="number" class="input h-10 w-20 text-sm" title="Percent of the points (negative = penalty)">
                                            <button type="button" class="text-slate-400 hover:text-rose-600" x-on:click="q.data.options.splice(j, 1)" aria-label="Remove option">✕</button>
                                        </div>
                                    </template>
                                    <button type="button" class="text-sm font-medium text-brand-600" x-on:click="q.data.options.push({ text: '', weight: 0, feedback: null })">+ Add option</button>
                                    <label x-show="q.type === 'mc'" class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="checkbox" x-model="q.data.any_correct" x-on:change="if (!q.data.any_correct) { let f = false; q.data.options.forEach((o) => { if (o.weight > 0 && !f) { f = true } else o.weight = 0 }) }"> More than one answer is right – student picks one, and any right one gets full points</label>
                                    <div x-show="q.type === 'multi'" class="space-y-1">
                                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="radio" :name="'aon' + q._k" :value="false" :checked="!q.data.all_or_nothing" x-on:change="q.data.all_or_nothing = false"> Partial points – each right tick earns its share</label>
                                        <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300"><input type="radio" :name="'aon' + q._k" :value="true" :checked="q.data.all_or_nothing" x-on:change="q.data.all_or_nothing = true"> All or nothing – must tick exactly all the right answers</label>
                                        <p class="text-xs text-slate-500" x-show="!q.data.all_or_nothing">The number is the share of points (e.g. 50 and 50; use −50 to take points off for a wrong tick).</p>
                                    </div>
                                </div>
                            </template>
                            {{-- True / false --}}
                            <template x-if="q.type === 'tf'">
                                <div class="flex gap-2">
                                    <button type="button" class="btn" :class="q.data.answer === true && 'btn-primary'" x-on:click="q.data.answer = true">True</button>
                                    <button type="button" class="btn" :class="q.data.answer === false && 'btn-primary'" x-on:click="q.data.answer = false">False</button>
                                </div>
                            </template>
                            {{-- Short answer --}}
                            <template x-if="q.type === 'short'">
                                <div class="space-y-2">
                                    <p class="text-xs text-slate-500">Accepted answers (capital letters don't matter):</p>
                                    <template x-for="(x, j) in q.data.answers" :key="j">
                                        <div class="flex gap-2"><input x-model="x.text" class="input h-10 flex-1" placeholder="Accepted answer"><input x-model.number="x.weight" type="number" class="input h-10 w-20 text-sm" title="Percent of the points"><button type="button" class="text-slate-400 hover:text-rose-600" x-on:click="q.data.answers.splice(j, 1)">✕</button></div>
                                    </template>
                                    <button type="button" class="text-sm font-medium text-brand-600" x-on:click="q.data.answers.push({ text: '', weight: 100 })">+ Another accepted answer</button>
                                </div>
                            </template>
                            {{-- Numeric --}}
                            <template x-if="q.type === 'numeric'">
                                <div class="space-y-2">
                                    <template x-for="(x, j) in q.data.answers" :key="j">
                                        <div class="flex flex-wrap items-center gap-2 text-sm">Answer <input x-model.number="x.value" type="number" step="any" class="input h-10 w-32"> ± <input x-model.number="x.tolerance" type="number" step="any" class="input h-10 w-24"> worth <input x-model.number="x.weight" type="number" class="input h-10 w-20">% <button type="button" class="text-slate-400 hover:text-rose-600" x-on:click="q.data.answers.splice(j, 1)">✕</button></div>
                                    </template>
                                    <button type="button" class="text-sm font-medium text-brand-600" x-on:click="q.data.answers.push({ value: 0, tolerance: 0, weight: 100 })">+ Another answer</button>
                                </div>
                            </template>
                            {{-- Matching --}}
                            <template x-if="q.type === 'matching'">
                                <div class="space-y-2">
                                    <template x-for="(p, j) in q.data.pairs" :key="j">
                                        <div class="flex items-center gap-2"><input x-model="p.left" class="input h-10 flex-1" placeholder="Item"><span class="text-slate-400">→</span><input x-model="p.right" class="input h-10 flex-1" placeholder="Matches"><button type="button" class="text-slate-400 hover:text-rose-600" x-on:click="q.data.pairs.splice(j, 1)">✕</button></div>
                                    </template>
                                    <button type="button" class="text-sm font-medium text-brand-600" x-on:click="q.data.pairs.push({ left: '', right: '' })">+ Add pair</button>
                                </div>
                            </template>
                            <template x-if="q.type === 'essay'"><p class="rounded-xl bg-slate-50 p-3 text-sm text-slate-500 dark:bg-white/5">Students type a long answer. You give the points in Results.</p></template>

                            <div class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3 text-sm dark:border-slate-800">
                                <label class="flex items-center gap-2">Points <input x-model.number="q.points" type="number" min="0" step="0.5" class="input h-9 w-20"></label>
                                <input x-model="q.feedback" class="input h-9 min-w-0 flex-1" placeholder="Feedback shown after (optional)">
                                <button type="button" class="text-slate-500 hover:text-slate-800" x-on:click.stop="move(i, -1)" :disabled="i === 0" aria-label="Move up">↑</button>
                                <button type="button" class="text-slate-500 hover:text-slate-800" x-on:click.stop="move(i, 1)" :disabled="i === questions.length - 1" aria-label="Move down">↓</button>
                                <button type="button" class="text-slate-500 hover:text-slate-800" x-on:click.stop="duplicate(i)">Duplicate</button>
                                <button type="button" class="text-rose-600" x-on:click.stop="questions.splice(i, 1)">Delete</button>
                            </div>
                        </div>
                    </div>
                </template>

                <div x-show="!questions.length" class="rounded-2xl border-2 border-dashed border-slate-300 p-10 text-center text-slate-500 dark:border-slate-700">
                    No questions yet. Add one on the right, or import GIFT.
                </div>
            </div>

            {{-- Side --}}
            <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
                <div class="card">
                    <h2 class="mb-3 font-semibold">Add a question</h2>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach(['mc' => 'Multiple choice', 'multi' => 'Checkboxes', 'tf' => 'True / False', 'short' => 'Short answer', 'numeric' => 'Number', 'matching' => 'Matching', 'essay' => 'Essay'] as $k => $label)
                            <button type="button" class="rounded-xl border border-slate-200 px-2 py-2 text-sm font-medium hover:border-brand-400 hover:bg-brand-50 dark:border-slate-700 dark:hover:bg-brand-500/10" x-on:click="add('{{ $k }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="card space-y-3 text-sm">
                    <h2 class="font-semibold">Settings</h2>
                    <label class="block"><span class="label">Time limit (minutes)</span><input name="time_limit" type="number" min="1" value="{{ $a->time_limit }}" class="input" placeholder="No limit"></label>
                    <label class="block"><span class="label">Attempts allowed</span>
                        <select name="attempts_allowed" class="input">@foreach([1, 2, 3, 5, 0] as $n)<option value="{{ $n }}" @selected($a->attempts_allowed === $n)>{{ $n ?: 'Unlimited' }}</option>@endforeach</select>
                        <span class="text-xs text-slate-500">The best attempt counts.</span>
                    </label>
                    <label class="flex items-center gap-2"><input type="hidden" name="shuffle_questions" value="0"><input type="checkbox" name="shuffle_questions" value="1" @checked($a->shuffle_questions)> Shuffle questions</label>
                    <label class="flex items-center gap-2"><input type="hidden" name="shuffle_answers" value="0"><input type="checkbox" name="shuffle_answers" value="1" @checked($a->shuffle_answers)> Shuffle answer choices</label>
                    <label class="block"><span class="label">Students see answers and score</span>
                        <select name="show_results" class="input">
                            @foreach(['after_submit' => 'Right after turning in', 'after_due' => 'After the due date', 'never' => 'Never (score only in grades)'] as $k => $v)<option value="{{ $k }}" @selected($a->show_results === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </label>
                    <button class="btn btn-primary w-full">Save quiz</button>
                </div>
            </aside>
        </div>
    </form>

    {{-- GIFT import --}}
    <details class="card mt-5">
        <summary class="cursor-pointer font-semibold">Import questions (GIFT format – Moodle compatible)</summary>
        <form method="post" action="{{ route('quiz.import', $a) }}" enctype="multipart/form-data" class="mt-3 space-y-3">
            @csrf
            <p class="text-sm text-slate-500">Paste GIFT text or upload a .txt/.gift file. Imported questions are added to the end (1 point each). Supported: multiple choice, checkboxes with %, true/false, short answer, number (with ± or range), matching, essay, feedback (#) and titles (::title::).</p>
            <textarea name="gift" rows="8" class="input font-mono text-xs" placeholder="::Capital:: What is the capital of the Philippines? { =Manila ~Cebu ~Davao }

The sun rises in the east. {T}

2 + 2 = ? {#4}

Match the planets. { =Earth -> 3rd =Mars -> 4th }"></textarea>
            <div class="flex flex-wrap items-center gap-3"><input type="file" name="file" accept=".txt,.gift" class="text-sm"><button class="btn btn-primary">Import</button></div>
        </form>
    </details>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function quizBuilder(initial) {
            let k = 0;
            const blank = (type) => ({
                mc: { options: [{ text: '', weight: 100, feedback: null }, { text: '', weight: 0, feedback: null }] },
                multi: { options: [{ text: '', weight: 50, feedback: null }, { text: '', weight: 50, feedback: null }, { text: '', weight: 0, feedback: null }] },
                tf: { answer: true }, short: { answers: [{ text: '', weight: 100 }] }, numeric: { answers: [{ value: 0, tolerance: 0, weight: 100 }] },
                matching: { pairs: [{ left: '', right: '' }, { left: '', right: '' }] }, essay: {},
            })[type];
            return {
                questions: initial.map((q) => ({ ...q, data: q.data && Object.keys(q.data).length ? q.data : blank(q.type), _k: ++k })),
                open: null,
                total() { return this.questions.reduce((s, q) => s + (Number(q.points) || 0), 0) },
                add(type) { const q = { type, title: null, text: '', points: 1, data: blank(type), feedback: null, _k: ++k }; this.questions.push(q); this.open = q._k; this.$nextTick(() => window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' })) },
                retype(q) { q.data = blank(q.type) },
                toggleCorrect(q, j) {
                    if (q.type === 'mc') { if (q.data.any_correct) { const o = q.data.options[j]; o.weight = o.weight > 0 ? 0 : 100 } else q.data.options.forEach((o, n) => o.weight = n === j ? 100 : 0); return }
                    const o = q.data.options[j]; o.weight = o.weight > 0 ? 0 : 100;
                    const right = q.data.options.filter((x) => x.weight > 0); right.forEach((x) => x.weight = Math.round(100 / right.length * 100) / 100);
                },
                move(i, d) { const q = this.questions.splice(i, 1)[0]; this.questions.splice(i + d, 0, q) },
                duplicate(i) { const c = JSON.parse(JSON.stringify(this.questions[i])); c._k = ++k; this.questions.splice(i + 1, 0, c) },
            };
        }
    </script>
</x-layouts.app>
