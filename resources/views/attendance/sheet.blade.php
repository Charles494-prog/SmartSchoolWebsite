@php
    $rows = $students->map(fn ($s) => [
        'id' => $s->id, 'name' => $s->full_name, 'photo' => $s->photo_path ? route('students.photo', $s) : null,
        'initials' => mb_substr($s->first_name, 0, 1).mb_substr($s->last_name, 0, 1),
        'status' => $records[$s->id]->status ?? null, 'time' => ampm($records[$s->id]->time_in ?? null), 'out' => ampm($records[$s->id]->time_out ?? null), 'qr' => ($records[$s->id]->method ?? null) === 'qr',
        'remarks' => $records[$s->id]->remarks ?? '',
        'history' => $history[$s->id] ?? (object) [],
        'prev' => ['present' => $stats[$s->id]['present'] ?? 0, 'late' => $stats[$s->id]['late'] ?? 0, 'absent' => $stats[$s->id]['absent'] ?? 0, 'excused' => $stats[$s->id]['excused'] ?? 0],
    ])->values();
@endphp
<x-layouts.app title="Attendance">
    <x-page-header title="Attendance" :subtitle="$section ? $section->label().' · '.$date->format('l, F j, Y') : null" />

    <div class="mb-6 flex flex-wrap items-center gap-2">
        <form class="contents">
            <select x-data name="section_id" class="input w-auto py-2" x-on:change="$el.form.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select>
            <div class="flex items-center rounded-xl ring-1 ring-slate-200 dark:ring-white/10">
                <a href="?section_id={{ $section?->id }}&date={{ $date->copy()->subDay()->toDateString() }}" class="rounded-l-xl p-2.5 text-slate-500 hover:bg-slate-50 dark:hover:bg-white/5" title="Previous day"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                <input type="date" name="date" value="{{ $date->toDateString() }}" max="{{ now()->toDateString() }}" class="border-0 bg-transparent py-2 text-sm focus:ring-0" x-data x-on:change="$el.form.submit()">
                @if($date->lt(today()))<a href="?section_id={{ $section?->id }}&date={{ $date->copy()->addDay()->toDateString() }}" class="rounded-r-xl p-2.5 text-slate-500 hover:bg-slate-50 dark:hover:bg-white/5" title="Next day"><x-icon name="chevron" class="h-4 w-4" /></a>@endif
            </div>
            @unless($date->isToday())<a href="?section_id={{ $section?->id }}" class="btn btn-sm">Today</a>@endunless
        </form>
        @if($section)<a href="{{ route('attendance.report', ['section_id' => $section->id]) }}" class="ml-auto text-sm font-medium text-slate-500 hover:text-slate-900">Monthly report</a>@endif
    </div>

    @if(! $section)
        <div class="card"><x-empty icon="users" title="No classes yet" text="You'll see your sections here once you're assigned as an adviser or subject teacher." /></div>
    @elseif($students->isEmpty())
        <div class="card"><x-empty icon="users" title="No students in {{ $section->label() }} yet" text="The registrar adds students to this section in Classes & sections." /></div>
    @else
    <form method="post" action="{{ route('attendance.save') }}" x-data="sheet(@js($rows))">
        @csrf
        <input type="hidden" name="section_id" value="{{ $section->id }}"><input type="hidden" name="date" value="{{ $date->toDateString() }}">

        {{-- Live summary --}}
        <div class="card mb-4 flex flex-wrap divide-x divide-slate-100 p-0 dark:divide-white/10">
            @foreach(['present' => ['Present', 'bg-emerald-500'], 'late' => ['Late', 'bg-amber-400'], 'absent' => ['Absent', 'bg-rose-500'], 'excused' => ['Excused', 'bg-sky-500'], 'none' => ['Not marked', 'bg-slate-300']] as $k => [$label, $dot])
                <div class="min-w-28 flex-1 px-5 py-4">
                    <div class="flex items-center gap-1.5 text-xs text-slate-500"><span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $label }}</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-900" x-text="count('{{ $k }}')"></div>
                </div>
            @endforeach
        </div>

        <div class="card p-0">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-100 px-5 py-3 dark:border-white/10">
                <span class="flex-1 text-sm text-slate-500"><span x-text="students.length"></span> students</span>
                <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <button type="button" role="switch" :aria-checked="showAll" @click="showAll=!showAll" :class="showAll ? 'bg-slate-900 dark:bg-white' : 'bg-slate-300 dark:bg-white/20'" class="relative inline-flex h-5 w-9 rounded-full transition">
                        <span :class="showAll ? 'translate-x-4 dark:bg-slate-900' : 'translate-x-0.5'" class="mt-0.5 inline-block h-4 w-4 rounded-full bg-white shadow transition"></span>
                    </button>
                    Show every day
                </label>
                <button type="button" @click="all('present')" class="btn btn-sm">Mark unmarked as present</button>
            </div>
            <ul class="divide-y divide-slate-100 dark:divide-white/5">
                <template x-for="(s, i) in students" :key="s.id">
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3">
                        <input type="hidden" :name="`status[${s.id}]`" :value="s.status" :disabled="!s.status">
                        <div class="flex min-w-0 flex-1 items-center gap-3" style="min-width: 14rem">
                            <template x-if="s.photo"><img :src="s.photo" class="h-9 w-9 rounded-full object-cover" alt=""></template>
                            <template x-if="!s.photo"><span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-500 dark:bg-white/10 dark:text-slate-300" x-text="s.initials"></span></template>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-medium text-slate-900" x-text="s.name"></div>
                                <div class="text-xs text-slate-500"><span x-show="s.time" x-text="'In ' + s.time + (s.out ? ' · Out ' + s.out : (s.qr ? ' · not checked out yet' : ''))"></span></div>
                            </div>
                        </div>

                        {{-- Attendance rate for the school year (updates live) --}}
                        <div class="w-32" :title="detail(s)">
                            <div class="flex items-baseline justify-between text-xs"><span class="text-slate-500">This year</span><span class="font-semibold" :class="rate(s) === null ? 'text-slate-400' : rate(s) < 80 ? 'text-rose-600' : 'text-slate-900 dark:text-slate-100'" x-text="rate(s) === null ? '—' : rate(s) + '%'"></span></div>
                            <div class="mt-1 h-1.5 rounded-full bg-slate-100 dark:bg-white/10"><div class="h-1.5 rounded-full transition-all" :class="rate(s) < 80 ? 'bg-rose-500' : 'bg-slate-800 dark:bg-slate-300'" :style="`width:${rate(s) ?? 0}%`"></div></div>
                        </div>

                        {{-- Status buttons --}}
                        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-white/5" role="radiogroup" :aria-label="'Status for ' + s.name">
                            <template x-for="opt in opts" :key="opt.k">
                                <button type="button" role="radio" :aria-checked="s.status === opt.k" @click="s.status = opt.k"
                                        :class="s.status === opt.k ? opt.on : 'text-slate-500 hover:text-slate-800 dark:hover:text-slate-200'"
                                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"><span x-show="s.status === opt.k" class="h-1.5 w-1.5 rounded-full" :class="opt.dot"></span><span x-text="opt.l"></span></button>
                            </template>
                        </div>

                        <div class="w-full sm:w-auto" x-show="s.status && s.status !== 'present' || s.remarks" x-cloak>
                            <input :name="`remarks[${s.id}]`" x-model="s.remarks" class="input py-1.5 text-sm sm:w-56" placeholder="Note (optional)">
                        </div>
                        <div x-show="showAll" x-cloak class="w-full pt-1 pl-12">
                            <div class="flex flex-wrap gap-1">
                                <template x-for="d in days" :key="d">
                                    <span class="flex h-6 w-6 items-center justify-center rounded text-[9px] font-semibold"
                                          :class="cell(s, d).cls" :title="label(d) + ': ' + cell(s, d).name" x-text="cell(s, d).short"></span>
                                </template>
                            </div>
                        </div>
                    </li>
                </template>
            </ul>
        </div>

        <div class="sticky bottom-0 z-20 mt-6 -mx-4 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:-mx-6 sm:px-6 dark:border-white/10 dark:bg-slate-900/90">
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span class="text-sm text-slate-500">Parents are notified for absent and late students.</span>
                <button class="btn btn-primary">Save attendance</button>
            </div>
        </div>
    </form>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function sheet(students) {
            const tone = {
                present: ['P', 'Present', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-200'],
                late: ['L', 'Late', 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200'],
                absent: ['A', 'Absent', 'bg-rose-500 text-white'],
                excused: ['E', 'Excused', 'bg-sky-100 text-sky-800 dark:bg-sky-500/20 dark:text-sky-200'],
            };
            return {
                students, days: @js($days), today: '{{ $date->toDateString() }}', showAll: localStorage.attHistory === '1',
                init() { this.$watch('showAll', v => localStorage.attHistory = v ? '1' : '0') },
                cell(s, d) {
                    const st = d === this.today ? s.status : (s.history[d] || null);
                    if (!st) return { short: '', name: 'No record', cls: 'bg-slate-100 dark:bg-white/5' };
                    const [short, name, cls] = tone[st]; return { short, name, cls: cls + (d === this.today ? ' ring-2 ring-slate-900 dark:ring-white' : '') };
                },
                label(d) { return new Date(d + 'T00:00').toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) },
                opts: [
                    { k: 'present', l: 'Present', on: 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-700 dark:text-white dark:ring-0', dot: 'bg-emerald-500' },
                    { k: 'late', l: 'Late', on: 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-700 dark:text-white dark:ring-0', dot: 'bg-amber-400' },
                    { k: 'absent', l: 'Absent', on: 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-700 dark:text-white dark:ring-0', dot: 'bg-rose-500' },
                    { k: 'excused', l: 'Excused', on: 'bg-white text-slate-900 shadow-sm ring-1 ring-slate-200 dark:bg-slate-700 dark:text-white dark:ring-0', dot: 'bg-sky-500' },
                ],
                count(k) { return this.students.filter(s => k === 'none' ? !s.status : s.status === k).length },
                all(k) { this.students.forEach(s => { if (!s.status) s.status = k }) },
                totals(s) { const t = { ...s.prev }; if (s.status) t[s.status]++; return t },
                // Attendance rate: (present + late) ÷ (school days − excused days)
                rate(s) { const t = this.totals(s), days = t.present + t.late + t.absent; return days ? Math.round((t.present + t.late) / days * 100) : null },
                detail(s) { const t = this.totals(s); return `${t.present} present · ${t.late} late · ${t.absent} absent · ${t.excused} excused (this school year)` },
            }
        }
    </script>
    @endif
</x-layouts.app>
