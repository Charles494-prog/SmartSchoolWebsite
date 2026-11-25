@php
    $palette = ['bg-indigo-100 text-indigo-900 ring-indigo-200', 'bg-emerald-100 text-emerald-900 ring-emerald-200', 'bg-amber-100 text-amber-900 ring-amber-200', 'bg-rose-100 text-rose-900 ring-rose-200',
                'bg-sky-100 text-sky-900 ring-sky-200', 'bg-violet-100 text-violet-900 ring-violet-200', 'bg-lime-100 text-lime-900 ring-lime-200', 'bg-orange-100 text-orange-900 ring-orange-200', 'bg-teal-100 text-teal-900 ring-teal-200', 'bg-fuchsia-100 text-fuchsia-900 ring-fuchsia-200'];
    $classes = $section->classSubjects->sortBy('subject.name')->values();
    $classData = $classes->map(fn ($cs, $i) => ['id' => $cs->id, 'subject' => $cs->subject->name, 'teacher' => $cs->teacher?->name, 'teacher_id' => $cs->teacher_id, 'color' => $palette[$i % count($palette)]]);
    $slotData = $classes->flatMap(fn ($cs) => $cs->slots->map(fn ($s) => ['id' => $s->id, 'class_id' => $cs->id, 'day' => $s->day_of_week, 'start' => substr($s->starts_at, 0, 5), 'end' => substr($s->ends_at, 0, 5), 'room' => $s->room]))->values();
@endphp
<x-layouts.app :title="$section->label()" :hideErrorSummary="true">
    <x-page-header :title="$section->label()" :back="route('sections.index')" backLabel="Classes & sections"
                   :subtitle="'S.Y. '.$section->schoolYear->name.' · Adviser: '.($section->adviser?->name ?? 'none').' · '.($section->room ?: 'no room').' · '.$students->count().'/'.$section->capacity.' students'">
        <a href="{{ route('sections.ids', $section) }}" target="_blank" class="btn"><x-icon name="qr" class="h-4 w-4" /> QR ID cards</a>
        <a href="{{ route('attendance.start', ['section' => $section->id]) }}" class="btn"><x-icon name="check-square" class="h-4 w-4" /> Attendance</a>
    </x-page-header>

    <div x-data="{ tab: '{{ request('tab', 'schedule') }}' }">
        <div class="mb-6 flex gap-1 border-b border-slate-200 dark:border-white/10">
            @foreach(['schedule' => 'Schedule', 'subjects' => 'Subjects & teachers', 'students' => 'Students ('.$students->count().')', 'details' => 'Details'] as $k => $label)
                <button @click="tab='{{ $k }}'" :class="tab==='{{ $k }}' ? 'border-brand-600 text-brand-700 dark:text-brand-200' : 'border-transparent text-slate-500 hover:text-slate-800'" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ============ SCHEDULE (drag & drop) ============ --}}
        <div x-show="tab==='schedule'" x-data="planner(@js($classData), @js($slotData), @js($busy), @js($section->room))">
            <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
                <p class="flex-1 text-slate-500"><b class="text-slate-800 dark:text-slate-100">Drag a subject onto the week</b> (or tap a subject, then tap a time). Drag a block to move it, drag its bottom edge to make it longer or shorter. Grey areas are times when that teacher or the room is busy elsewhere.</p>
                <label class="flex items-center gap-2">Length
                    <select x-model.number="minutes" class="input w-auto py-1.5"><option value="30">30 min</option><option value="45">45 min</option><option value="60">1 hour</option><option value="90">1½ hours</option><option value="120">2 hours</option></select>
                </label>
                <label class="flex items-center gap-2">Room
                    <select x-model="room" class="input w-auto py-1.5"><option value="">{{ $section->room ? $section->room.' (this section\'s room)' : 'No room' }}</option>@foreach($rooms as $k => $v)@if($k !== $section->room)<option value="{{ $k }}">{{ $k }}</option>@endif @endforeach</select>
                </label>
            </div>

            <p class="mb-4 text-xs text-slate-500">New blocks go into <b x-text="room || '{{ $section->room ?: 'no room' }}'"></b>.
                <a class="link" :href="'{{ route('timetable') }}?room=' + encodeURIComponent(room || '{{ $section->room }}')">See that room's whole week</a> ·
                <a class="link" href="{{ route('timetable', ['section_id' => $section->id]) }}">This section's week</a></p>
            <div x-show="error" x-cloak x-transition class="mb-4 flex items-start gap-2 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800 ring-1 ring-rose-200"><x-icon name="alert" class="h-5 w-5 shrink-0" /><span class="flex-1" x-text="error"></span><button @click="error=''"><x-icon name="x" class="h-4 w-4" /></button></div>

            <div class="grid gap-4 lg:grid-cols-[15rem_1fr]">
                {{-- Subject palette --}}
                <div class="card h-fit space-y-2 p-3 lg:sticky lg:top-20">
                    <div class="px-1 pb-1 text-xs font-semibold text-slate-500">SUBJECTS</div>
                    <template x-for="c in classes" :key="c.id">
                        <div draggable="true" @dragstart="dragNew(c, $event)" @dragend="dragEnd()" @click="pick = pick && pick.id === c.id ? null : c"
                             :class="[c.color, pick && pick.id === c.id ? 'ring-2 ring-brand-600' : 'ring-1']" class="cursor-grab rounded-xl px-3 py-2 text-sm select-none active:cursor-grabbing">
                            <div class="font-semibold" x-text="c.subject"></div>
                            <div class="flex justify-between text-xs opacity-75"><span x-text="c.teacher || 'No teacher yet'"></span><span x-text="hours(c.id)"></span></div>
                        </div>
                    </template>
                    <p x-show="!classes.length" class="p-2 text-sm text-slate-500">No subjects yet – add them in “Subjects & teachers”.</p>
                </div>

                {{-- Week grid --}}
                <div class="card overflow-x-auto p-0">
                    <div class="flex min-w-[44rem]">
                        <div class="w-14 shrink-0 border-r border-slate-100 pt-10 dark:border-white/10">
                            <template x-for="h in hoursList"><div class="relative text-right text-[11px] text-slate-400" :style="`height:${rowH * 2}px`"><span class="absolute -top-2 right-2" x-text="h"></span></div></template>
                        </div>
                        <template x-for="d in days" :key="d.n">
                            <div class="min-w-0 flex-1 border-r border-slate-100 last:border-r-0 dark:border-white/10">
                                <div class="sticky top-0 z-10 h-10 border-b border-slate-100 bg-white text-center text-xs leading-10 font-semibold text-slate-600 dark:border-white/10 dark:bg-slate-900" x-text="d.label"></div>
                                <div class="relative" :style="`height:${rows * rowH}px`"
                                     @dragover.prevent="over(d.n, $event)" @dragleave="ghost=null" @drop.prevent="drop(d.n, $event)" @click="tapPlace(d.n, $event)">
                                    <template x-for="r in rows"><div class="border-b border-slate-50 dark:border-white/5" :class="r % 2 === 0 ? 'border-slate-100' : ''" :style="`height:${rowH}px`"></div></template>
                                    {{-- busy elsewhere (teacher or room) --}}
                                    <template x-for="b in busyFor(d.n)">
                                        <div class="pointer-events-none absolute inset-x-1 rounded-lg bg-[repeating-linear-gradient(45deg,#e2e8f0_0,#e2e8f0_6px,#f8fafc_6px,#f8fafc_12px)] px-2 py-1 text-[10px] text-slate-500 ring-1 ring-slate-200 dark:opacity-40"
                                             :style="pos(b.start, b.end)" :title="b.why" x-text="b.why"></div>
                                    </template>
                                    {{-- placed blocks --}}
                                    <template x-for="s in slots.filter(x => x.day === d.n)" :key="s.id">
                                        <div draggable="true" @dragstart="dragMove(s, $event)" @dragend="dragEnd()" @click.stop
                                             :class="cls(s.class_id).color" class="group absolute inset-x-1 cursor-grab overflow-hidden rounded-lg px-2 py-1 text-xs ring-1 select-none" :style="pos(s.start, s.end)">
                                            <div class="truncate font-semibold" x-text="cls(s.class_id).subject"></div>
                                            <div class="truncate opacity-75" x-text="ap(s.start) + '–' + ap(s.end) + (s.room ? ' · ' + s.room : '')"></div>
                                            <div class="truncate opacity-75" x-text="cls(s.class_id).teacher || ''"></div>
                                            <button @click.stop="remove(s)" class="absolute top-1 right-1 hidden rounded bg-white/80 p-0.5 group-hover:block" title="Remove"><x-icon name="x" class="h-3 w-3" /></button>
                                            <div @mousedown.stop.prevent="startResize(s, $event)" class="absolute inset-x-0 bottom-0 h-2 cursor-ns-resize"></div>
                                        </div>
                                    </template>
                                    {{-- drop preview --}}
                                    <div x-show="ghost && ghost.day === d.n" class="pointer-events-none absolute inset-x-1 rounded-lg border-2 border-dashed border-brand-500 bg-brand-50/60 px-2 py-1 text-xs font-semibold text-brand-700"
                                         :style="ghost ? pos(ghost.start, ghost.end) : ''" x-text="ghost ? ap(ghost.start) + '–' + ap(ghost.end) : ''"></div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- ============ SUBJECTS & TEACHERS ============ --}}
        <div x-show="tab==='subjects'" x-cloak class="max-w-3xl">
            <div class="card p-0">
                <table class="table">
                    <thead><tr><th>Subject</th><th>Teacher</th><th>Hours / week</th><th></th></tr></thead>
                    <tbody>
                    @forelse($classes as $cs)
                        <tr>
                            <td><span class="font-semibold">{{ $cs->subject->name }}</span> <span class="text-xs text-slate-400">{{ $cs->subject->code }}</span></td>
                            <td>
                                <form method="post" action="{{ route('classes.update', $cs) }}">@csrf @method('put')
                                    <select x-data name="teacher_id" class="input py-1.5" x-on:change="$el.form.submit()"><option value="">— choose teacher —</option>@foreach($teachers as $k => $v)<option value="{{ $k }}" @selected($cs->teacher_id == $k)>{{ $v }}</option>@endforeach</select>
                                </form>
                            </td>
                            <td>{{ round($cs->slots->sum(fn ($s) => \Carbon\Carbon::parse($s->starts_at)->diffInMinutes(\Carbon\Carbon::parse($s->ends_at))) / 60, 1) }}</td>
                            <td class="text-right"><form x-data method="post" action="{{ route('classes.destroy', $cs) }}" x-on:submit="if (!confirm('Remove {{ $cs->subject->name }} from this section?')) $event.preventDefault()">@csrf @method('delete')<button class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Remove"><x-icon name="x" class="h-4 w-4" /></button></form></td>
                        </tr>
                    @empty
                        <tr><td colspan="4">
                            @if($subjects->isEmpty())
                                <x-empty icon="book" title="No subjects for {{ $section->gradeLevel->name }} yet" text="Subjects are made once per grade level, then added to each section here."
                                         action="Add a subject for {{ $section->gradeLevel->name }}" :href="route('crud.create', 'subjects')" :open="true"
                                         :steps="['Open <b>Subjects</b> and click <b>Add subject</b>.', 'Type the code and name, and choose <b>'.e($section->gradeLevel->name).'</b> as the grade level.', 'Set how the grade is computed (the weights must total 100) and save.', 'Come back to this section → <b>Subjects &amp; teachers</b>, pick the subject and click <b>Add</b>.']" />
                            @else
                                <x-empty icon="book" title="No subjects in this section yet" text="Use the “Add subject” box below to add each subject this section takes."
                                         :steps="['In <b>Add subject</b> below, choose a subject.', 'Choose its teacher (or leave it empty and assign later).', 'Click <b>Add</b>. Repeat for every subject.', 'Then open the <b>Schedule</b> tab to set the class times.']" />
                            @endif
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-sm text-slate-500">A teacher can't be assigned if they already teach somewhere else at one of this subject's times.</p>
            @if($teachers->isEmpty())
                <div class="card mt-4"><x-empty icon="users" title="No teachers yet" text="Teachers need an account before they can be assigned to a subject."
                    action="Add a teacher" :href="route('crud.create', 'employees')"
                    :steps="['Open <b>Employees</b> and click <b>Add employee</b>.', 'Fill in the name and position (e.g. Teacher I) and the monthly salary.', 'Under <b>SmartSchool login</b>, type their email and choose <b>Teacher</b>.', 'Save. Write down the temporary password shown – they change it at first login.', 'Come back here and choose them in the <b>Teacher</b> column.']" /></div>
            @endif
            @if($subjects->isNotEmpty())
                <form method="post" action="{{ route('classes.store', $section) }}" class="card mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-field name="subject_id" label="Add subject" type="select" :options="$subjects" required class="min-w-48 flex-1" />
                    <x-field name="teacher_id" label="Teacher" type="select" :options="$teachers" class="min-w-48 flex-1" />
                    <button class="btn btn-primary">Add</button>
                </form>
            @endif
        </div>

        {{-- ============ STUDENTS ============ --}}
        <div x-show="tab==='students'" x-cloak class="grid gap-4 lg:grid-cols-2">
            <div class="card">
                <h2 class="h2 mb-3">Class list</h2>
                <ol class="divide-y divide-slate-100 text-sm dark:divide-white/5">
                    @forelse($students as $i => $st)
                        @php $en = $enrollmentIds[$st->id] ?? null; @endphp
                        <li class="flex items-center gap-3 py-2"><span class="w-6 text-right text-xs text-slate-400">{{ $i + 1 }}</span><a class="link flex-1" href="{{ route('students.show', $st) }}">{{ $st->full_name }}</a>
                            @if($en)<form method="post" action="{{ route('sections.students.remove', [$section, $en]) }}">@csrf @method('delete')<button class="text-xs text-slate-400 hover:text-rose-600">Remove</button></form>@endif</li>
                    @empty
                        <li><x-empty icon="users" title="No students in this section yet" text="Students enrolled in {{ $section->gradeLevel->name }} without a section appear in “Add students”."
                            :steps="['Approve an application in <b>Applications</b>, or add a student in <b>Students</b> and enroll them in '.e($section->gradeLevel->name).'.', 'Come back here – they show up under <b>Add students</b>.', 'Tick their names and click <b>Add selected</b>.']" /></li>
                    @endforelse
                </ol>
            </div>
            <div class="card">
                <h2 class="h2 mb-1">Add students</h2>
                <p class="muted mb-3">Enrolled in {{ $section->gradeLevel->name }} this school year without a section.</p>
                @if($waiting->isEmpty())
                    <p class="muted">Nobody is waiting.</p>
                @else
                    <form method="post" action="{{ route('sections.students.assign', $section) }}" x-data>
                        @csrf
                        <label class="mb-2 flex gap-2 text-sm font-semibold"><input type="checkbox" class="rounded" @change="$root.querySelectorAll('.pick').forEach(c => c.checked = $event.target.checked)"> Select all</label>
                        <div class="max-h-72 space-y-1 overflow-y-auto text-sm">
                            @foreach($waiting as $e)<label class="flex gap-2"><input type="checkbox" name="enrollment_ids[]" value="{{ $e->id }}" class="pick rounded"> {{ $e->student->full_name }}</label>@endforeach
                        </div>
                        <button class="btn btn-primary mt-3 w-full">Add selected</button>
                    </form>
                @endif
            </div>
        </div>

        {{-- ============ DETAILS ============ --}}
        <div x-show="tab==='details'" x-cloak class="card max-w-3xl">
            <form method="post" action="{{ route('sections.update', $section) }}" class="grid gap-4 sm:grid-cols-2">
                @csrf @method('put')
                <x-field name="name" label="Section name" :value="$section->name" required />
                <x-field name="grade_level_id" label="Grade level" type="select" :options="$levels" :value="$section->grade_level_id" required />
                <x-field name="school_year_id" label="School year" type="select" :options="$years" :value="$section->school_year_id" required />
                <x-field name="adviser_id" label="Adviser" type="select" :options="$teachers" :value="$section->adviser_id" />
                <div class="sm:col-span-2" x-data="{ caps: @js($capacities) }">
                    <x-field name="room" label="Room" type="select" :options="$rooms" :value="$section->room" x-on:change="if (caps[$event.target.value]) $refs.cap.value = caps[$event.target.value]" />
                    <div class="mt-4"><label class="label" for="cap">Capacity</label><input x-ref="cap" id="cap" type="number" name="capacity" value="{{ old('capacity', $section->capacity) }}" class="input" required>@error('capacity')<p class="field-error">{{ $message }}</p>@enderror</div>
                </div>
                <div class="flex gap-2 sm:col-span-2"><button class="btn btn-primary">Save</button></div>
            </form>
            <form x-data method="post" action="{{ route('sections.destroy', $section) }}" class="mt-6 border-t border-slate-100 pt-4" x-on:submit="if (!confirm('Delete this section?')) $event.preventDefault()">@csrf @method('delete')<button class="btn btn-danger btn-sm">Delete section</button></form>
        </div>
    </div>

    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
        function planner(classes, slots, busy, sectionRoom) {
            const csrf = '{{ csrf_token() }}', START = 7 * 60, END = 18 * 60;
            const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m };
            const ap = t => { const [h, m] = t.split(':').map(Number); return ((h % 12) || 12) + ':' + String(m).padStart(2, '0') + ' ' + (h < 12 ? 'AM' : 'PM') };
            const toTime = m => String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0');
            const call = (url, method, body) => fetch(url, { method, headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: body ? JSON.stringify(body) : undefined })
                .then(async r => { const j = await r.json().catch(() => ({})); if (!r.ok) throw new Error(j.message || 'Could not save.'); return j });
            return {
                ap, classes, slots, busy, minutes: 60, room: '', pick: null, drag: null, ghost: null, error: '', rowH: 24,
                rows: (END - START) / 30,
                days: [1, 2, 3, 4, 5, 6].map(n => ({ n, label: ['', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'][n] })),
                get hoursList() { const a = []; for (let m = START; m < END; m += 60) a.push(ap(toTime(m))); return a },
                cls(id) { return this.classes.find(c => c.id === id) || {} },
                hours(id) { const m = this.slots.filter(s => s.class_id === id).reduce((t, s) => t + toMin(s.end) - toMin(s.start), 0); return m ? (m / 60) + ' h/wk' : '' },
                pos(a, b) { const t = (toMin(a) - START) / 30 * this.rowH, h = Math.max(14, (toMin(b) - toMin(a)) / 30 * this.rowH - 2); return `top:${t}px;height:${h}px` },
                busyFor(day) {
                    const active = this.drag ? this.drag.cls : this.pick;
                    if (!active) return [];
                    const room = this.drag && this.drag.slot ? this.drag.slot.room : (this.room || sectionRoom);
                    return this.busy.filter(b => b.day === day && ((active.teacher_id && b.teacher_id === active.teacher_id) || (room && b.room === room)))
                        .map(b => ({ ...b, why: (active.teacher_id && b.teacher_id === active.teacher_id ? 'Teacher busy' : b.room + ' in use') + ': ' + b.label }));
                },
                at(e) { const y = e.offsetY ?? (e.clientY - e.currentTarget.getBoundingClientRect().top); return Math.min(END - 15, Math.max(START, START + Math.round(y / this.rowH * 30 / 15) * 15)) },
                len() { return this.drag && this.drag.slot ? toMin(this.drag.slot.end) - toMin(this.drag.slot.start) : this.minutes },
                dragNew(c, e) { this.drag = { cls: c }; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'x') },
                dragMove(s, e) { this.drag = { cls: this.cls(s.class_id), slot: s }; e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', 'x') },
                dragEnd() { this.drag = null; this.ghost = null },
                over(day, e) { if (!this.drag) return; const rect = e.currentTarget.getBoundingClientRect(); const s = this.at({ offsetY: e.clientY - rect.top }); this.ghost = { day, start: toTime(s), end: toTime(Math.min(END, s + this.len())) } },
                async drop(day, e) {
                    if (!this.drag) return;
                    const rect = e.currentTarget.getBoundingClientRect(); const start = toTime(this.at({ offsetY: e.clientY - rect.top }));
                    const d = this.drag; this.dragEnd();
                    if (d.slot) await this.move(d.slot, day, start); else await this.place(d.cls, day, start);
                },
                async tapPlace(day, e) { if (!this.pick) return; const rect = e.currentTarget.getBoundingClientRect(); await this.place(this.pick, day, toTime(this.at({ offsetY: e.clientY - rect.top }))) },
                async place(c, day, start) {
                    this.error = '';
                    try { this.slots.push(await call(`{{ url('/classes') }}/${c.id}/place`, 'POST', { day, start, minutes: this.minutes, room: this.room || null })) } catch (err) { this.error = err.message }
                },
                async move(s, day, start, minutes) {
                    this.error = '';
                    try { const r = await call(`{{ url('/slots') }}/${s.id}`, 'PATCH', { day, start, minutes }); Object.assign(s, r) } catch (err) { this.error = err.message }
                },
                async remove(s) {
                    try { await call(`{{ url('/slots') }}/${s.id}`, 'DELETE'); this.slots = this.slots.filter(x => x.id !== s.id) } catch (err) { this.error = err.message }
                },
                startResize(s, e) {
                    const y0 = e.clientY, len0 = toMin(s.end) - toMin(s.start), endOrig = s.end;
                    const onMove = ev => { const m = Math.max(15, Math.round((len0 + (ev.clientY - y0) / this.rowH * 30) / 15) * 15); s.end = toTime(Math.min(END, toMin(s.start) + m)) };
                    const onUp = async () => {
                        window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp);
                        if (s.end !== endOrig) { const keep = s.end; s.end = endOrig; await this.move(s, s.day, s.start, toMin(keep) - toMin(s.start)) }
                    };
                    window.addEventListener('mousemove', onMove); window.addEventListener('mouseup', onUp);
                },
            }
        }
    </script>
</x-layouts.app>
