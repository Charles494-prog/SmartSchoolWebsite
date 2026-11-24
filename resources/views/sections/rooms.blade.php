@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];
    $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
    $startMin = 7 * 60; $endMin = 18 * 60; $px = 1.1; // pixels per minute
    $byRoom = $slots->groupBy('room');
@endphp
<x-layouts.app title="All rooms">
    <x-page-header title="All rooms" subtitle="Every room's schedule side by side. Pick a day, or print every room's full week.">
        <a href="{{ route('timetable', ['room' => '__all', 'print' => 1]) }}" class="btn"><x-icon name="file" class="h-4 w-4" /> Weekly view of every room</a>
        <button onclick="window.print()" class="btn"><x-icon name="file" class="h-4 w-4" /> Print</button>
    </x-page-header>

    @if($rooms->isEmpty())
        <div class="card"><x-empty icon="home" title="No rooms yet" text="Add your rooms first."><a href="{{ route('crud.create', 'rooms') }}" class="btn btn-primary btn-sm">Add rooms</a></x-empty></div>
    @elseif(! $print)
        {{-- One day, all rooms as columns --}}
        <div class="mb-5 flex flex-wrap items-center gap-2">
            <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm font-medium dark:bg-white/5">
                @foreach($days as $n => $d)
                    <a href="{{ route('timetable', ['room' => '__all', 'day' => $n]) }}" class="rounded-lg px-3 py-1.5 {{ $day === $n ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:text-slate-800' }}">{{ substr($d, 0, 3) }}</a>
                @endforeach
            </div>
            <a href="{{ route('timetable', ['room' => $rooms->first()]) }}" class="text-sm text-slate-500 hover:text-slate-900">Single room view</a>
        </div>
        <div class="card overflow-x-auto p-0">
            <div class="flex" style="min-width: {{ 4 + $rooms->count() * 11 }}rem">
                <div class="w-16 shrink-0 border-r border-slate-100 dark:border-white/10">
                    <div class="h-11 border-b border-slate-100 dark:border-white/10"></div>
                    <div class="relative" style="height: {{ ($endMin - $startMin) * $px }}px">
                        @for($m = $startMin; $m < $endMin; $m += 60)<div class="absolute right-2 -translate-y-1/2 text-[11px] text-slate-400" style="top: {{ ($m - $startMin) * $px }}px">{{ ampm(sprintf('%02d:00', $m / 60)) }}</div>@endfor
                    </div>
                </div>
                @foreach($rooms as $room)
                    @php $today = ($byRoom[$room] ?? collect())->where('day_of_week', $day); @endphp
                    <div class="min-w-[10rem] flex-1 border-r border-slate-100 last:border-r-0 dark:border-white/10">
                        <a href="{{ route('timetable', ['room' => $room]) }}" class="flex h-11 items-center justify-between border-b border-slate-100 px-3 text-sm font-semibold hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5">
                            {{ $room }} <span class="text-xs font-normal text-slate-400">{{ $today->count() }}</span>
                        </a>
                        <div class="relative" style="height: {{ ($endMin - $startMin) * $px }}px">
                            @for($m = $startMin; $m < $endMin; $m += 60)<div class="absolute inset-x-0 border-t border-slate-100 dark:border-white/5" style="top: {{ ($m - $startMin) * $px }}px"></div>@endfor
                            @foreach($today as $s)
                                @php $top = ($toMin($s->starts_at) - $startMin) * $px; $h = max(18, ($toMin($s->ends_at) - $toMin($s->starts_at)) * $px - 2); @endphp
                                <div class="absolute inset-x-1 overflow-hidden rounded-lg bg-brand-50 px-2 py-1 text-[11px] leading-tight ring-1 ring-brand-100 dark:bg-brand-500/15 dark:ring-brand-500/20" style="top: {{ $top }}px; height: {{ $h }}px"
                                     title="{{ $s->classSubject->subject->name }} · {{ $s->classSubject->section->label() }} · {{ $s->classSubject->teacher?->name }} · {{ ampm($s->starts_at) }}–{{ ampm($s->ends_at) }}">
                                    <div class="truncate font-semibold text-brand-900 dark:text-brand-100">{{ $s->classSubject->subject->name }}</div>
                                    <div class="truncate text-slate-600 dark:text-slate-300">{{ $s->classSubject->section->label() }}</div>
                                    <div class="truncate text-slate-500">{{ ampm($s->starts_at) }}–{{ ampm($s->ends_at) }} · {{ $s->classSubject->teacher?->name }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @else
        {{-- Every room's full week, one after another (one per printed page) --}}
        <a href="{{ route('timetable', ['room' => '__all']) }}" class="link mb-4 inline-block text-sm">Back to day view</a>
        @foreach($rooms as $room)
            @php $mine = $byRoom[$room] ?? collect(); $times = $mine->map(fn ($s) => substr($s->starts_at, 0, 5).'|'.substr($s->ends_at, 0, 5))->unique()->sort()->values(); @endphp
            <div class="card mb-6 break-after-page overflow-x-auto p-0">
                <h2 class="border-b border-slate-100 px-5 py-3 text-lg font-semibold dark:border-white/10">{{ $room }}</h2>
                @if($times->isEmpty())
                    <p class="px-5 py-6 text-sm text-slate-500">No classes scheduled in this room.</p>
                @else
                <table class="w-full min-w-[48rem] table-fixed text-xs">
                    <thead><tr><th class="w-20 bg-slate-50 px-2 py-2 text-left font-semibold text-slate-500 dark:bg-white/5">Time</th>@foreach($days as $d)<th class="bg-slate-50 px-2 py-2 text-left font-semibold text-slate-500 dark:bg-white/5">{{ $d }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($times as $t)
                        @php [$st, $en] = explode('|', $t); @endphp
                        <tr>
                            <td class="border-t border-slate-100 px-2 py-1.5 align-top font-semibold text-slate-500 dark:border-white/5">{{ ampm($st) }}–{{ ampm($en) }}</td>
                            @foreach($days as $dn => $d)
                                <td class="border-t border-l border-slate-100 px-2 py-1.5 align-top dark:border-white/5">
                                    @foreach($mine->filter(fn ($s) => $s->day_of_week == $dn && substr($s->starts_at, 0, 5) === $st && substr($s->ends_at, 0, 5) === $en) as $s)
                                        <div class="font-semibold">{{ $s->classSubject->subject->name }}</div>
                                        <div class="text-slate-500">{{ $s->classSubject->section->label() }} · {{ $s->classSubject->teacher?->name }}</div>
                                    @endforeach
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        @endforeach
    @endif
</x-layouts.app>
