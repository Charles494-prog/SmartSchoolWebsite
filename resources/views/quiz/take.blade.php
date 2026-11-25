<x-layouts.app :title="$a->title">
    @php $deadline = $attempt->deadline(); @endphp
    <form method="post" action="{{ route('quiz.submit', $attempt) }}" x-data="quizTaker({{ $deadline ? $deadline->timestamp * 1000 : 'null' }}, {{ $questions->count() }})" x-ref="form" x-on:change="autosave()" x-on:submit="leaving = true" class="mx-auto max-w-2xl">
        @csrf
        {{-- Sticky bar: progress + timer --}}
        <div class="sticky top-16 z-20 -mx-4 mb-5 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-900/90">
            <div class="flex items-center gap-3">
                <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $a->title }}</p><p class="text-xs text-slate-500"><span x-text="answered"></span> of {{ $questions->count() }} answered · <span x-text="saved"></span></p></div>
                <span x-show="left !== null" class="rounded-full px-3 py-1 font-mono text-sm font-bold" :class="left < 60 ? 'bg-rose-100 text-rose-700' : 'bg-slate-100 text-slate-700'" x-text="clock()"></span>
            </div>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-brand-600 transition-all" :style="`width: ${answered / {{ max(1, $questions->count()) }} * 100}%`"></div></div>
        </div>

        <div class="space-y-4">
            @foreach($questions as $q)
                @php $name = 'answers['.$q->id.']'; $old = $attempt->answers[$q->id] ?? null; $d = $q->data; @endphp
                <fieldset class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900" data-q>
                    <legend class="sr-only">Question {{ $loop->iteration }}</legend>
                    <div class="mb-3 flex gap-3"><span class="font-bold text-brand-600">{{ $loop->iteration }}.</span><p class="flex-1 whitespace-pre-line font-medium">{{ $q->text }}</p><span class="shrink-0 text-xs text-slate-400">{{ $q->points + 0 }} pt{{ $q->points == 1 ? '' : 's' }}</span></div>
                    @switch($q->type)
                        @case('mc')
                            @foreach($q->optionOrder ?? array_keys($d['options'] ?? []) as $i)
                                <label class="mb-2 flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:hover:bg-white/5 dark:has-[:checked]:bg-brand-500/10">
                                    <input type="radio" name="{{ $name }}" value="{{ $i }}" @checked((string) $old === (string) $i)> {{ $d['options'][$i]['text'] }}
                                </label>
                            @endforeach
                            @break
                        @case('multi')
                            <p class="mb-2 text-xs text-slate-500">Choose all that apply.</p>
                            @foreach($q->optionOrder ?? array_keys($d['options'] ?? []) as $i)
                                <label class="mb-2 flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:hover:bg-white/5 dark:has-[:checked]:bg-brand-500/10">
                                    <input type="checkbox" name="{{ $name }}[]" value="{{ $i }}" @checked(in_array((string) $i, array_map('strval', (array) $old), true))> {{ $d['options'][$i]['text'] }}
                                </label>
                            @endforeach
                            @break
                        @case('tf')
                            <div class="grid grid-cols-2 gap-2">
                                @foreach(['1' => 'True', '0' => 'False'] as $v => $label)
                                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 p-3 font-medium has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50 dark:border-slate-700 dark:has-[:checked]:bg-brand-500/10"><input type="radio" name="{{ $name }}" value="{{ $v }}" class="sr-only" @checked((string) $old === $v)> {{ $label }}</label>
                                @endforeach
                            </div>
                            @break
                        @case('short')
                            <input name="{{ $name }}" value="{{ is_string($old) ? $old : '' }}" class="input" placeholder="Your answer" autocomplete="off">
                            @break
                        @case('numeric')
                            <input name="{{ $name }}" value="{{ is_scalar($old) ? $old : '' }}" type="number" step="any" class="input w-48" placeholder="Number">
                            @break
                        @case('matching')
                            @php $rights = collect($d['pairs'] ?? [])->pluck('right')->unique()->shuffle(crc32($attempt->id.'-'.$q->id))->values(); @endphp
                            @foreach($d['pairs'] ?? [] as $i => $p)
                                <div class="mb-2 grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                                    <span class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-white/5">{{ $p['left'] }}</span><span class="text-slate-400">→</span>
                                    <select name="{{ $name }}[{{ $i }}]" class="input"><option value="">Choose…</option>@foreach($rights as $r)<option @selected((((array) $old)[$i] ?? null) === $r)>{{ $r }}</option>@endforeach</select>
                                </div>
                            @endforeach
                            @break
                        @case('essay')
                            <textarea name="{{ $name }}" rows="6" class="input" placeholder="Write your answer">{{ is_string($old) ? $old : '' }}</textarea>
                            @break
                    @endswitch
                </fieldset>
            @endforeach
        </div>

        <button class="btn btn-primary mt-6 w-full min-h-12 text-base" x-on:click="if (answered < total && !confirm('Some questions have no answer. Turn in anyway?')) $event.preventDefault()">Turn in</button>
    </form>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function quizTaker(deadline, total) {
            return {
                total, answered: 0, saved: 'Not saved yet', left: null, leaving: false, timer: null,
                init() {
                    this.count();
                    if (deadline) { const tick = () => { this.left = Math.max(0, Math.round((deadline - Date.now()) / 1000)); if (this.left === 0 && !this.leaving) { this.leaving = true; this.$refs.form.submit() } }; tick(); setInterval(tick, 1000) }
                    window.addEventListener('beforeunload', (e) => { if (!this.leaving) { e.preventDefault(); e.returnValue = '' } });
                },
                count() { this.answered = [...this.$el.querySelectorAll('[data-q]')].filter((f) => [...f.querySelectorAll('input,textarea,select')].some((i) => (i.type === 'radio' || i.type === 'checkbox') ? i.checked : i.value.trim() !== '')).length },
                clock() { const m = Math.floor(this.left / 60), s = this.left % 60; return `${m}:${String(s).padStart(2, '0')}` },
                autosave() {
                    this.count(); clearTimeout(this.timer);
                    this.timer = setTimeout(async () => {
                        const fd = new FormData(this.$refs.form); fd.append('save_only', '1');
                        try { await fetch(this.$refs.form.action, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } }); this.saved = 'Saved ' + new Date().toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' }) } catch (e) { this.saved = 'Offline – will save when you turn in' }
                    }, 800);
                },
            };
        }
    </script>
</x-layouts.app>
