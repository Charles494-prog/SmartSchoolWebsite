@php $steps = \App\Http\Controllers\AllocationController::STEPS; @endphp
<x-layouts.app title="Assign sections">
    <x-page-header title="Assign sections" :subtitle="$a ? $a->gradeLevel->name.' · '.$a->schoolYear->name : 'Put a grade level’s students into sections – at the start of the year or after promotion.'" />

    {{-- stepper --}}
    <ol class="mb-6 grid grid-cols-5 gap-2 text-xs sm:text-sm" aria-label="Steps">
        @foreach($steps as $n => $label)
            @php $done = $n < $step; $here = $n === $step; $href = $a && $n > 1 && $n <= 5 && ($n < $step || $a->status !== 'committed') ? route('allocation.step', [$a, $n]) : ($n === 1 ? route('allocation.index') : null); @endphp
            <li @if($here) aria-current="step" @endif>
                @php $inner = '<span class="h-1.5 rounded-full '.($n <= $step ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700').'"></span><span class="'.($here ? 'font-semibold text-slate-900 dark:text-white' : ($done ? 'text-slate-700 dark:text-slate-300' : 'text-slate-500')).'"><span class="hidden sm:inline">'.$n.'.</span> '.e($label).'</span>'; @endphp
                @if($href && ! $here)<a href="{{ $href }}" class="flex min-h-11 flex-col gap-1.5 hover:underline">{!! $inner !!}</a>@else<span class="flex min-h-11 flex-col gap-1.5">{!! $inner !!}</span>@endif
            </li>
        @endforeach
    </ol>

    @if($step === 1)
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
            <form method="post" action="{{ route('allocation.start') }}" class="card space-y-4">@csrf
                <h2 class="h2">Which grade level?</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <x-field name="school_year_id" label="School year" type="select" :options="$years->pluck('name', 'id')" :value="$years->firstWhere('is_current', true)?->id ?? $years->first()?->id" required />
                    <x-field name="grade_level_id" label="Grade level" type="select" :options="$levels->pluck('name', 'id')" required />
                </div>
                @if($waiting->isNotEmpty())
                    <div class="rounded-xl bg-amber-50 p-3 text-sm text-amber-900 dark:bg-amber-500/10 dark:text-amber-200">
                        <p class="font-semibold">Students without a section</p>
                        <ul class="mt-1 space-y-0.5">@foreach($waiting as $w)<li>{{ $levels->firstWhere('id', $w->grade_level_id)?->name }} · {{ $years->firstWhere('id', $w->school_year_id)?->name }}: <b>{{ $w->n }}</b></li>@endforeach</ul>
                    </div>
                @endif
                <button class="btn btn-primary min-h-11">Next: sections</button>
            </form>
            <div class="card">
                <h2 class="h2 mb-2">Recent allocations</h2>
                @forelse($recent as $r)
                    <div class="flex flex-wrap items-center gap-2 border-t border-slate-100 py-2.5 text-sm first:border-0 dark:border-slate-800">
                        <span class="min-w-0 flex-1">{{ $r->gradeLevel->name }} · {{ $r->schoolYear->name }}<br><span class="text-xs text-slate-500">{{ \App\Models\SectionAllocation::METHODS[$r->method] }} · {{ ucfirst($r->status) }}{{ $r->committed_at ? ' '.$r->committed_at->format('M j, Y') : '' }}</span></span>
                        @if(in_array($r->status, ['draft', 'open']))<a href="{{ route('allocation.step', [$r, 4]) }}" class="btn min-h-11">Continue</a>@endif
                        @if($r->status === 'committed')
                            <form method="post" action="{{ route('allocation.undo', $r) }}" x-data x-on:submit="if (!confirm('Undo this allocation? Students go back to the sections they had before.')) $event.preventDefault()">@csrf<button class="btn min-h-11">Undo</button></form>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-slate-500">None yet.</p>
                @endforelse
            </div>
        </div>

    @elseif($step === 2)
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-slate-600 dark:text-slate-400"><b>{{ $enrollments->count() }}</b> students to place · seats in sections: <b>{{ $sections->sum('capacity') }}</b>
                        @if($sections->sum('capacity') < $enrollments->count())<span class="ml-1 font-semibold text-rose-800">– not enough seats ({{ $enrollments->count() - $sections->sum('capacity') }} short)</span>@endif</p>
                    <form method="post" action="{{ route('allocation.rooms', $a) }}">@csrf<button class="btn min-h-11">Generate home rooms for this grade</button></form>
                </div>
                @forelse($sections as $s)
                    <form method="post" action="{{ route('allocation.section', [$a, $s]) }}" class="card grid gap-3 sm:grid-cols-[minmax(0,1fr)_100px_minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end">@csrf
                        <label><span class="label">Section name</span><input name="name" value="{{ $s->name }}" class="input min-h-11" required maxlength="60"></label>
                        <label><span class="label">Capacity</span><input name="capacity" type="number" min="1" max="500" value="{{ $s->capacity }}" class="input min-h-11" required></label>
                        <label><span class="label">Adviser</span><select name="adviser_id" class="input min-h-11"><option value="">None yet</option>@foreach($teachers as $id => $n)<option value="{{ $id }}" @selected($s->adviser_id == $id)>{{ $n }}</option>@endforeach</select></label>
                        <label><span class="label">Home room</span><select name="room" class="input min-h-11"><option value="">None</option>@foreach($rooms as $r)<option value="{{ $r->name }}" @selected($s->room === $r->name)>{{ $r->name }} · {{ $r->capacity }} seats</option>@endforeach</select></label>
                        <button class="btn min-h-11">Save</button>
                    </form>
                @empty
                    <div class="card"><x-empty icon="layers" title="No sections yet" text="Add the first {{ $a->gradeLevel->name }} section on the right." /></div>
                @endforelse
                @if($errors->any())<p class="field-error">{{ $errors->first() }}</p>@endif
            </div>
            <div class="space-y-3">
                <form method="post" action="{{ route('allocation.section', $a) }}" class="card space-y-3">@csrf
                    <h2 class="h2">Add a section</h2>
                    <x-field name="name" label="Name" placeholder="e.g. Sampaguita" required />
                    <x-field name="capacity" label="Capacity" type="number" min="1" max="500" value="40" required />
                    <x-field name="adviser_id" label="Adviser" type="select" :options="['' => 'None yet'] + $teachers->all()" />
                    <x-field name="room" label="Home room" type="select" :options="['' => 'None'] + $rooms->pluck('name', 'name')->all()" />
                    <button class="btn min-h-11 w-full">Add section</button>
                </form>
                <a href="{{ route('allocation.step', [$a, 3]) }}" class="btn btn-primary min-h-11 w-full {{ $sections->isEmpty() ? 'pointer-events-none opacity-50' : '' }}" @if($sections->isEmpty()) aria-disabled="true" @endif>Next: choose method</a>
            </div>
        </div>

    @elseif($step === 3)
        @php $o = $a->options ?? []; @endphp
        <form method="post" action="{{ route('allocation.method', $a) }}" class="space-y-4" x-data="{ m: @js(old('method', $a->method)) }">@csrf
            <fieldset class="grid gap-3 md:grid-cols-3">
                <legend class="sr-only">Method</legend>
                @foreach(['random' => ['Randomize', 'The system places everyone, balanced by seats. Same settings → same result; you can reshuffle.', 'repeat'],
                          'choice' => ['Students choose', 'Students pick a section online until the deadline (first come, first served). Anyone left is placed automatically.', 'users'],
                          'manual' => ['Manual', 'Drag students into sections yourself on a board.', 'list']] as $k => [$t, $d, $i])
                    @php $off = $k === 'choice' && ! $choiceAllowed; @endphp
                    <label class="card flex cursor-pointer flex-col gap-2 ring-2 {{ $off ? 'cursor-not-allowed opacity-60' : '' }}" :class="m === '{{ $k }}' ? 'ring-brand-600' : 'ring-transparent'">
                        <span class="flex items-center gap-2"><input type="radio" name="method" value="{{ $k }}" x-model="m" class="h-4 w-4 accent-brand-600" @disabled($off)><x-icon :name="$i" class="h-5 w-5 text-slate-500" /><span class="font-semibold">{{ $t }}</span></span>
                        <span class="text-sm text-slate-600 dark:text-slate-400">{{ $d }}</span>
                        @if($off)<span class="text-xs font-medium text-amber-800">Online mode only – students can’t reach the school system in LAN mode.</span>@endif
                    </label>
                @endforeach
            </fieldset>
            <div class="card space-y-3" x-show="m === 'random' || m === 'choice'" x-cloak>
                <h2 class="h2">Options</h2>
                <label class="flex min-h-11 items-center gap-3"><input type="hidden" name="gender" value="0"><input type="checkbox" name="gender" value="1" class="h-5 w-5 accent-brand-600" @checked($o['gender'] ?? true)> Balance boys and girls</label>
                <label class="flex min-h-11 items-center gap-3"><input type="hidden" name="snake" value="0"><input type="checkbox" name="snake" value="1" class="h-5 w-5 accent-brand-600" @checked($o['snake'] ?? true)> Mix by general average (“snake draft”, so every section gets top, middle and lower students)</label>
                <label class="flex min-h-11 items-center gap-3"><input type="hidden" name="siblings" value="0"><input type="checkbox" name="siblings" value="1" class="h-5 w-5 accent-brand-600" @checked($o['siblings'] ?? true)> Keep siblings (same parent/guardian) together</label>
                <div class="grid gap-3 md:grid-cols-2">
                    <x-field name="together" type="textarea" label="Keep together (one group per line, names separated by commas)" :value="$o['together_text'] ?? ''" placeholder="Juan Santos, Mark Reyes" />
                    <x-field name="apart" type="textarea" label="Keep apart (one group per line)" :value="$o['apart_text'] ?? ''" placeholder="Paolo Cruz, Carlo Garcia" />
                </div>
            </div>
            <div class="card" x-show="m === 'choice'" x-cloak>
                <x-field name="deadline" type="datetime-local" label="Choosing closes on" :value="optional($a->deadline)->format('Y-m-d\TH:i') ?? now()->addDays(3)->setTime(17, 0)->format('Y-m-d\TH:i')" />
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-400">Students get a notification. Seats are first come, first served.</p>
            </div>
            <div class="flex flex-wrap gap-2"><a href="{{ route('allocation.step', [$a, 2]) }}" class="btn min-h-11">Back</a><button class="btn btn-primary min-h-11">Next: preview</button></div>
        </form>

    @elseif($step === 4 && $a->status === 'open')
        <div class="card mb-4 flex flex-wrap items-center gap-3" x-data x-init="setInterval(() => location.reload(), 10000)">
            <x-icon name="clock" class="h-6 w-6 text-slate-500" />
            <div class="flex-1"><p class="font-semibold">Students are choosing – closes {{ $a->deadline->format('M j, Y') }} at {{ ampm($a->deadline->format('H:i')) }}</p>
                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $enrollments->whereNotNull('section_id')->count() }} of {{ $enrollments->count() }} have chosen. Counts refresh every 10 seconds.</p></div>
            <form method="post" action="{{ route('allocation.close', $a) }}" x-on:submit="if (!confirm('Close choosing now and place everyone left?')) $event.preventDefault()">@csrf<button class="btn btn-primary min-h-11">Close now &amp; place the rest</button></form>
        </div>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($sections as $s)
                @php $n = $enrollments->where('section_id', $s->id)->count(); @endphp
                <div class="card"><p class="font-semibold">{{ $s->name }}</p><p class="text-sm text-slate-600 dark:text-slate-400">{{ $n }} / {{ $s->capacity }} seats taken</p>
                    <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><div class="h-full {{ $n >= $s->capacity ? 'bg-rose-600' : 'bg-brand-600' }}" style="width: {{ min(100, round($n / max(1, $s->capacity) * 100)) }}%"></div></div></div>
            @endforeach
        </div>

    @elseif($step === 4)
        @php
            $cards = $enrollments->map(fn ($e) => ['id' => $e->id, 'name' => $e->student->full_name, 'sex' => strtolower((string) $e->student->sex), 'ga' => $students[$e->id]['ga'], 'tags' => $tags[$e->id] ?? []])->sortBy('name')->values();
            $cols = $sections->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'cap' => (int) $s->capacity])->values();
        @endphp
        <form method="post" action="{{ route('allocation.plan', $a) }}" x-data="board(@js($cards), @js($cols), @js((object) $plan), @js($a->locked ?? []))" x-on:submit="busy = true">@csrf
            <template x-for="c in cards" :key="'p' + c.id"><input type="hidden" :name="'plan[' + c.id + ']'" :value="plan[c.id] ?? ''"></template>
            <template x-for="id in locked" :key="'l' + id"><input type="hidden" name="locked[]" :value="id"></template>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                <p class="mr-auto text-sm text-slate-600 dark:text-slate-400">Drag a student to another section, or use the menu on the card. <x-icon name="key" class="inline h-3.5 w-3.5" /> = locked (stays put when you reshuffle).</p>
                @if($a->method === 'random')
                    <button type="submit" formaction="{{ route('allocation.reshuffle', $a) }}" class="btn min-h-11" x-on:click="if (!confirm('Reshuffle everyone who isn’t locked? Save first if you moved anyone by hand.')) $event.preventDefault()"><x-icon name="repeat" class="h-4 w-4" /> Reshuffle</button>
                @endif
                <button class="btn min-h-11" :disabled="busy">Save</button>
                <button name="next" value="1" class="btn btn-primary min-h-11" :disabled="busy">Next: confirm</button>
            </div>
            <p x-show="over.length" x-cloak class="mb-3 rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800 ring-1 ring-rose-200" role="alert">Over capacity: <span x-text="over.join(', ')"></span>. Move students or raise the capacity in step 2.</p>

            <div class="flex gap-3 overflow-x-auto pb-3">
                <template x-for="col in [{ id: null, name: 'Not placed', cap: 0 }, ...cols]" :key="'c' + col.id">
                    <section class="flex w-72 shrink-0 flex-col rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-800 dark:bg-white/5"
                             x-on:dragover.prevent x-on:drop.prevent="drop(col.id)" :aria-label="col.name">
                        <header class="border-b border-slate-200 p-3 dark:border-slate-800">
                            <p class="flex items-center justify-between font-semibold"><span class="truncate" x-text="col.name"></span><span class="text-sm tabular-nums" :class="col.id && count(col.id) > col.cap ? 'text-rose-700' : 'text-slate-600 dark:text-slate-300'" x-text="col.id ? count(col.id) + ' / ' + col.cap : count(null)"></span></p>
                            <template x-if="col.id">
                                <div>
                                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700"><div class="h-full" :class="count(col.id) > col.cap ? 'bg-rose-600' : 'bg-brand-600'" :style="'width:' + Math.min(100, count(col.id) / Math.max(1, col.cap) * 100) + '%'"></div></div>
                                    <p class="mt-1.5 text-xs text-slate-600 dark:text-slate-400"><span x-text="sex(col.id, 'male')"></span> boys · <span x-text="sex(col.id, 'female')"></span> girls · avg <span x-text="avg(col.id)"></span></p>
                                </div>
                            </template>
                        </header>
                        <ul class="min-h-24 flex-1 space-y-1.5 overflow-y-auto p-2" style="max-height: 60vh">
                            <template x-for="c in cards.filter((x) => (plan[x.id] ?? null) == col.id)" :key="c.id">
                                <li draggable="true" x-on:dragstart="drag = c.id" class="rounded-xl bg-white p-2.5 text-sm shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700" :class="locked.includes(c.id) ? 'ring-2 ring-brand-600' : 'cursor-grab'">
                                    <div class="flex items-start gap-2">
                                        <span class="min-w-0 flex-1"><span class="block break-words font-medium" x-text="c.name"></span>
                                            <span class="text-xs text-slate-600 dark:text-slate-400" x-text="(c.sex ? c.sex[0].toUpperCase() + c.sex.slice(1) : '') + (c.ga ? ' · GA ' + c.ga : '')"></span>
                                            <template x-for="t in c.tags"><span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200" x-text="t"></span></template></span>
                                        <button type="button" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg hover:bg-slate-100 dark:hover:bg-white/10" :class="locked.includes(c.id) ? 'text-brand-700' : 'text-slate-400'" x-on:click="lock(c.id)" :aria-pressed="locked.includes(c.id)" :aria-label="(locked.includes(c.id) ? 'Unlock ' : 'Lock ') + c.name"><x-icon name="key" class="h-4 w-4" /></button>
                                    </div>
                                    <label class="sr-only" :for="'mv' + c.id">Move to section</label>
                                    <select :id="'mv' + c.id" class="input mt-1.5 h-9 text-xs" x-on:change="move(c.id, $el.value === '' ? null : Number($el.value))">
                                        <option value="" :selected="!plan[c.id]">Not placed</option>
                                        <template x-for="s in cols" :key="s.id"><option :value="s.id" :selected="plan[c.id] == s.id" x-text="s.name"></option></template>
                                    </select>
                                </li>
                            </template>
                        </ul>
                    </section>
                </template>
            </div>
        </form>
        <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
            document.addEventListener('alpine:init', () => {
                Alpine.data('board', (cards, cols, plan, locked) => ({
                    cards, cols, plan: { ...plan }, locked: locked.map(Number), drag: null, busy: false,
                    count(id) { return this.cards.filter((c) => (this.plan[c.id] ?? null) == id).length },
                    sex(id, s) { return this.cards.filter((c) => this.plan[c.id] == id && c.sex === s).length },
                    avg(id) { const g = this.cards.filter((c) => this.plan[c.id] == id && c.ga).map((c) => Number(c.ga)); return g.length ? (g.reduce((a, b) => a + b, 0) / g.length).toFixed(1) : '—' },
                    get over() { return this.cols.filter((c) => this.count(c.id) > c.cap).map((c) => c.name) },
                    move(id, to) { if (to === null) delete this.plan[id]; else this.plan[id] = to },
                    drop(to) { if (this.drag !== null) { this.move(this.drag, to); this.drag = null } },
                    lock(id) { this.locked = this.locked.includes(id) ? this.locked.filter((x) => x !== id) : [...this.locked, id] },
                }));
            });
        </script>

    @elseif($step === 5)
        @php
            $moves = collect($plan)->filter(fn ($sid, $eid) => $enrollments->firstWhere('id', $eid)?->section_id !== $sid)->count();
            $unplaced = $enrollments->count() - count($plan);
        @endphp
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5"><tr><th class="px-4 py-2.5">Section</th><th class="px-3 py-2.5 text-right">Students</th><th class="px-3 py-2.5 text-right">Boys / girls</th><th class="px-3 py-2.5 text-right">Avg GA</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach($sections as $s)
                            @php $st = $stats[$s->id]; @endphp
                            <tr><td class="px-4 py-2.5 font-medium">{{ $s->name }}</td><td class="px-3 py-2.5 text-right tabular-nums {{ $st['count'] > $s->capacity ? 'font-semibold text-rose-700' : '' }}">{{ $st['count'] }} / {{ $s->capacity }}</td>
                                <td class="px-3 py-2.5 text-right tabular-nums">{{ $st['male'] }} / {{ $st['female'] }}</td><td class="px-3 py-2.5 text-right tabular-nums">{{ $st['avg'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card space-y-3">
                <p><b>{{ count($plan) }}</b> students placed · <b>{{ $moves }}</b> change section{{ $unplaced ? ' · ' : '' }}@if($unplaced)<b class="text-amber-800">{{ $unplaced }} not placed</b>@endif</p>
                @if(collect($stats)->contains(fn ($st, $sid) => $st['count'] > $sections->firstWhere('id', $sid)->capacity))
                    <p class="rounded-xl bg-rose-50 p-3 text-sm font-medium text-rose-800">A section is over capacity. You can still confirm, but consider moving students first.</p>
                @endif
                <p class="text-sm text-slate-600 dark:text-slate-400">This is logged. You can undo it later from the first step (Recent allocations).</p>
                <form method="post" action="{{ route('allocation.commit', $a) }}" x-data="{ busy: false }" x-on:submit="if (!confirm('Put {{ count($plan) }} students into these sections now?')) { $event.preventDefault(); return } busy = true">@csrf
                    <button class="btn btn-primary min-h-11 w-full" :disabled="busy" x-text="busy ? 'Saving…' : 'Confirm allocation'">Confirm allocation</button>
                </form>
                <a href="{{ route('allocation.step', [$a, 4]) }}" class="btn min-h-11 w-full">Back to preview</a>
            </div>
        </div>
    @endif
</x-layouts.app>
