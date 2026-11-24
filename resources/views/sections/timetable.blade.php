@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $times = $slots->flatten()->map(fn ($s) => substr($s->starts_at, 0, 5).'|'.substr($s->ends_at, 0, 5))->unique()->sort()->values();
@endphp
<x-layouts.app title="Timetable">
    <x-page-header title="{{ $title ?: 'Timetable' }}" subtitle="Weekly schedule. Rooms, teachers and sections can never be double-booked.">
        <a href="{{ route('timetable', ['room' => '__all']) }}" class="btn"><x-icon name="layers" class="h-4 w-4" /> All rooms</a>
        <button onclick="window.print()" class="btn"><x-icon name="file" class="h-4 w-4" /> Print</button>
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2" x-data>
        <div class="inline-flex flex-wrap rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5">
            @php
                $tabs = ['room' => ['By room', ['room' => request('room') && request('room') !== '__all' ? request('room') : $rooms->first()]],
                         'section' => ['By section', ['section_id' => request('section_id') ?: $sections->keys()->first()]],
                         'teacher' => [$teachers->isEmpty() ? 'My schedule' : 'By teacher', request('teacher_id') ? ['teacher_id' => request('teacher_id')] : []],
                         'subject' => ['By subject', ['subject' => request('subject') ?: $subjects->first()]]];
            @endphp
            @foreach($tabs as $m => [$label, $params])
                <a href="{{ route('timetable', $params) }}" class="rounded-lg px-3.5 py-1.5 {{ $mode === $m ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:text-slate-800' }}">{{ $label }}</a>
            @endforeach
            <a href="{{ route('timetable', ['room' => '__all']) }}" class="rounded-lg px-3.5 py-1.5 text-slate-500 hover:text-slate-800">All rooms</a>
        </div>
        @if($mode === 'room')
            <select class="input w-auto py-2" onchange="location='?room='+encodeURIComponent(this.value)"><option value="__all">All rooms</option>@foreach($rooms as $r)<option @selected(request('room') === $r)>{{ $r }}</option>@endforeach</select>
            @if($rooms->isEmpty())<span class="muted">No rooms yet. <a class="link" href="{{ route('crud.create', 'rooms') }}">Add rooms</a></span>@endif
        @elseif($mode === 'section')
            <select class="input w-auto py-2" onchange="location='?section_id='+this.value">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected(request('section_id') == $k)>{{ $v }}</option>@endforeach</select>
        @elseif($mode === 'subject')
            <select class="input w-auto py-2" onchange="location='?subject='+encodeURIComponent(this.value)">@foreach($subjects as $sub)<option @selected(request('subject') === $sub)>{{ $sub }}</option>@endforeach</select>
        @elseif($teachers->isNotEmpty())
            <select class="input w-auto py-2" onchange="location='?teacher_id='+this.value"><option value="">Me</option>@foreach($teachers as $k => $v)<option value="{{ $k }}" @selected(request('teacher_id') == $k)>{{ $v }}</option>@endforeach</select>
        @endif
    </div>

    <div class="card overflow-x-auto p-0">
        @if($times->isEmpty())
            <x-empty icon="clock" title="Nothing scheduled here yet" text="Class times are added inside each section (Classes & sections → open a section → + Time)." />
        @else
        <table class="w-full min-w-[56rem] table-fixed border-collapse text-sm">
            <thead><tr><th class="w-28 border-b border-slate-100 bg-slate-50 px-3 py-3 text-left text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-white/5">Time</th>@foreach($days as $d)<th class="border-b border-slate-100 bg-slate-50 px-3 py-3 text-left text-xs font-semibold text-slate-500 dark:border-white/10 dark:bg-white/5">{{ $d }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($times as $t)
                @php [$st, $en] = explode('|', $t); @endphp
                <tr>
                    <td class="border-b border-slate-100 px-3 py-2 align-top text-xs font-semibold text-slate-500 dark:border-white/5">{{ ampm($st) }}<br><span class="font-normal">{{ ampm($en) }}</span></td>
                    @foreach($days as $dn => $d)
                        <td class="border-b border-l border-slate-100 p-1.5 align-top dark:border-white/5">
                            @foreach(($slots[$dn] ?? collect())->filter(fn ($s) => substr($s->starts_at, 0, 5) === $st && substr($s->ends_at, 0, 5) === $en) as $s)
                                <div class="rounded-lg bg-brand-50 px-2.5 py-2 text-xs ring-1 ring-brand-100 dark:bg-brand-500/15 dark:ring-brand-500/20">
                                    <div class="font-semibold text-brand-900 dark:text-brand-100">{{ $s->classSubject->subject->name }}</div>
                                    <div class="text-slate-600 dark:text-slate-300">{{ $mode === 'section' ? ($s->classSubject->teacher?->name ?? 'No teacher') : $s->classSubject->section->label() }}</div>
                                    <div class="text-slate-500">{{ match ($mode) { 'room' => $s->classSubject->teacher?->name ?? '', 'subject' => ($s->classSubject->teacher?->name ?? 'No teacher').' · '.$s->room, default => $s->room } }}</div>
                                </div>
                            @endforeach
                        </td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</x-layouts.app>
