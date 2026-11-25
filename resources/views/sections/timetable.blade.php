@php
    $dayNames = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
    if ($slots->contains('day_of_week', 6)) { $dayNames[6] = 'Saturday'; }
    $byDay = $slots->sortBy('starts_at')->groupBy('day_of_week');
    $today = now()->dayOfWeekIso;
    $line2 = match ($mode) {
        'section' => fn ($s) => ($s->classSubject->teacher?->name ?? 'No teacher').' · '.$s->room,
        'room' => fn ($s) => $s->classSubject->section->label().' · '.($s->classSubject->teacher?->name ?? ''),
        default => fn ($s) => $s->classSubject->section->label().' · '.$s->room,
    };
    $tabs = ['section' => ['By section', ['section_id' => request('section_id') ?: $sections->keys()->first()]],
             'teacher' => [$teachers->isEmpty() ? 'My schedule' : 'By teacher', request('teacher_id') ? ['teacher_id' => request('teacher_id')] : ($teachers->isEmpty() ? [] : ['teacher_id' => $teachers->keys()->first()])],
             'room' => ['By room', ['room' => request('room') ?: $rooms->first()]],
             'subject' => ['By subject', ['subject' => request('subject') ?: $subjects->first()]]];
@endphp
<x-layouts.app title="Timetable">
    <div class="screen-only">
        <x-page-header title="{{ $title ?: 'Timetable' }}" subtitle="Weekly schedule. A room, teacher or section can never be double-booked.">
            <span class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold {{ $conflicts ? 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' }}" role="status">
                <x-icon name="{{ $conflicts ? 'alert' : 'check-circle' }}" class="h-4 w-4" /> Conflicts: {{ $conflicts }}</span>
            <a href="{{ route('timetable', ['room' => '__all']) }}" class="btn min-h-11"><x-icon name="layers" class="h-4 w-4" /> All rooms</a>
            <button type="button" x-data x-on:click="window.print()" class="btn min-h-11"><x-icon name="file" class="h-4 w-4" /> Print</button>
        </x-page-header>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <nav class="inline-flex flex-wrap rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5" aria-label="Timetable views">
                @foreach($tabs as $m => [$label, $params])
                    <a href="{{ route('timetable', $params) }}" @if($mode === $m) aria-current="page" @endif class="flex min-h-10 items-center rounded-lg px-3.5 {{ $mode === $m ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <label class="sr-only" for="tt-pick">Choose</label>
            @if($mode === 'room')
                <select id="tt-pick" x-data class="input min-h-11 w-auto" x-on:change="location='?room='+encodeURIComponent($el.value)">@foreach($rooms as $r)<option @selected(request('room') === $r)>{{ $r }}</option>@endforeach</select>
                @if($rooms->isEmpty())<span class="muted">No rooms yet. <a class="link" href="{{ route('crud.index', 'rooms') }}">Add rooms</a></span>@endif
            @elseif($mode === 'section')
                <select id="tt-pick" x-data class="input min-h-11 w-auto" x-on:change="location='?section_id='+$el.value">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected(request('section_id') == $k)>{{ $v }}</option>@endforeach</select>
            @elseif($mode === 'subject')
                <select id="tt-pick" x-data class="input min-h-11 w-auto" x-on:change="location='?subject='+encodeURIComponent($el.value)">@foreach($subjects as $sub)<option @selected(request('subject') === $sub)>{{ $sub }}</option>@endforeach</select>
            @elseif($teachers->isNotEmpty())
                <select id="tt-pick" x-data class="input min-h-11 w-auto" x-on:change="location='?teacher_id='+$el.value">@foreach($teachers as $k => $v)<option value="{{ $k }}" @selected(request('teacher_id') == $k)>{{ $v }}</option>@endforeach</select>
            @endif
        </div>

        @if($slots->isEmpty())
            <div class="card"><x-empty icon="clock" title="Nothing scheduled here yet" text="Class times are added inside each section." action="Open Classes & sections" :href="route('sections.index')"
                :steps="['Open <b>Classes &amp; sections</b> and click a section.', 'In <b>Subjects &amp; teachers</b>, add the subjects and teachers.', 'Open the <b>Schedule</b> tab and click <b>+ Time</b> for each class.', 'Come back here to see the weekly timetable.']" /></div>
        @elseif($mode === 'subject')
            <div class="space-y-5">
                @foreach($subjectRows as $level => $rows)
                    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <h2 class="border-b border-slate-100 px-5 py-3 font-semibold dark:border-slate-800">{{ $level }} <span class="text-sm font-normal text-slate-500">· {{ $rows->count() }} {{ str('class')->plural($rows->count()) }}</span></h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500 dark:bg-white/5"><tr><th class="px-5 py-2.5">Section</th><th class="px-3 py-2.5">Time</th><th class="px-3 py-2.5">Days</th><th class="px-3 py-2.5">Teacher</th><th class="px-3 py-2.5">Room</th></tr></thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    @foreach($rows as $r)
                                        @php $s = $r['slot']; @endphp
                                        <tr>
                                            <td class="px-5 py-2.5 font-medium"><a class="hover:underline" href="{{ route('timetable', ['section_id' => $s->classSubject->section_id]) }}">{{ $s->classSubject->section->name }}</a></td>
                                            <td class="whitespace-nowrap px-3 py-2.5 tabular-nums">{{ ampm($s->starts_at) }} – {{ ampm($s->ends_at) }}</td>
                                            <td class="whitespace-nowrap px-3 py-2.5">{{ $r['days'] }}</td>
                                            <td class="px-3 py-2.5">{{ $s->classSubject->teacher?->name ?? 'No teacher yet' }}</td>
                                            <td class="whitespace-nowrap px-3 py-2.5">{{ $s->room }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endforeach
            </div>
        @else
            @include('sections._grid', ['cols' => $dayNames, 'byCol' => $byDay, 'nowCols' => [$today], 'line2' => $line2, 'colW' => '9.5rem'])
        @endif
    </div>

    @if($mode === 'subject')
        @include('sections._print', ['sheets' => $slots->groupBy(fn ($s) => $s->classSubject->section->label())->map(fn ($g) => $g)->sortKeys()->mapWithKeys(fn ($g, $k) => [$title.' – '.$k => $g])])
    @else
        @include('sections._print', ['sheets' => [$title ?: 'Timetable' => $slots]])
    @endif
</x-layouts.app>
