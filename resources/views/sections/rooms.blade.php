@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $byRoom = $slots->groupBy('room');
    $dayByRoom = $rooms->mapWithKeys(fn ($r) => [$r => ($byRoom[$r] ?? collect())->where('day_of_week', $day)->values()]);
    $gradeName = $levels->firstWhere('id', $grade)?->name;
@endphp
<x-layouts.app title="All rooms">
    <div class="screen-only">
        <x-page-header title="All rooms{{ $gradeName ? ' – '.$gradeName : '' }}" subtitle="Each grade level has its own block of rooms. Click a class to see its details.">
            <span class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-sm font-semibold {{ $conflicts ? 'bg-rose-50 text-rose-800 ring-1 ring-rose-200' : 'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200' }}" role="status">
                <x-icon name="{{ $conflicts ? 'alert' : 'check-circle' }}" class="h-4 w-4" /> Conflicts: {{ $conflicts }}</span>
            <button type="button" x-data x-on:click="window.print()" class="btn min-h-11"><x-icon name="file" class="h-4 w-4" /> Print each room’s week</button>
        </x-page-header>

        <div class="mb-5 flex flex-wrap items-center gap-2">
            <label class="sr-only" for="grade-pick">Grade level</label>
            <select id="grade-pick" x-data class="input min-h-11 w-auto font-medium" x-on:change="location='?room=__all&day={{ $day }}&grade='+$el.value">
                @foreach($levels as $l)<option value="{{ $l->id }}" @selected($l->id === $grade)>{{ $l->name }}</option>@endforeach
            </select>
            <nav class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5" aria-label="Day">
                @foreach(array_slice($days, 0, 5, true) + ($slots->contains('day_of_week', 6) ? [6 => 'Saturday'] : []) as $n => $d)
                    <a href="{{ route('timetable', ['room' => '__all', 'day' => $n, 'grade' => $grade]) }}" @if($day === $n) aria-current="page" @endif class="flex min-h-10 items-center rounded-lg px-3 {{ $day === $n ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-300' }}">{{ substr($d, 0, 3) }}{{ $n === now()->dayOfWeekIso ? ' ·' : '' }}</a>
                @endforeach
            </nav>
            <a href="{{ route('timetable') }}" class="btn min-h-11">Week views</a>
            <a href="{{ route('crud.index', ['rooms', 'grade_level_id' => $grade]) }}" class="text-sm text-slate-600 hover:text-slate-900 hover:underline dark:text-slate-300">Manage rooms</a>
        </div>

        @if($levels->isEmpty())
            <div class="card"><x-empty icon="layers" title="No sections yet" text="Create sections first, then generate their rooms."><a href="{{ route('sections.index') }}" class="btn btn-primary min-h-11">Classes &amp; sections</a></x-empty></div>
        @elseif($rooms->isEmpty())
            <div class="card"><x-empty icon="home" title="{{ $gradeName }} has no rooms yet" text="Generate one room per section from the Rooms page."><a href="{{ route('crud.index', 'rooms') }}" class="btn btn-primary min-h-11">Generate rooms</a></x-empty></div>
        @else
            @include('sections._grid', ['cols' => $rooms->mapWithKeys(fn ($r) => [$r => $r])->all(), 'byCol' => $dayByRoom, 'nowCols' => $day === now()->dayOfWeekIso ? $rooms->all() : [],
                'colLink' => fn ($r) => route('timetable', ['room' => $r]), 'line2' => fn ($s) => $s->classSubject->section->name.' · '.($s->classSubject->teacher?->name ?? 'No teacher')])
        @endif
    </div>

    @include('sections._print', ['sheets' => $rooms->mapWithKeys(fn ($r) => [$r => $byRoom[$r] ?? collect()])])
</x-layouts.app>
