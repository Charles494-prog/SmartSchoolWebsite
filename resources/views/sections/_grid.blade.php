{{--
    Proportional timetable grid.
    $cols: [key => label], $byCol: key => slots, $nowCols: keys that get the red "now" line,
    $colLink: optional fn(key) => url for the column header, $line2: fn(slot) => second line on the card.
--}}
@php
    $toMin = fn ($t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
    $all = collect($byCol)->flatten();
    $startMin = min(7 * 60, $all->isEmpty() ? 7 * 60 : (int) floor($all->min(fn ($s) => $toMin($s->starts_at)) / 30) * 30);
    $endMin = max(17 * 60 + 30, $all->isEmpty() ? 0 : (int) ceil($all->max(fn ($s) => $toMin($s->ends_at)) / 30) * 30);
    $px = 1.4; // pixels per minute → 84px per hour
    $lunch = array_map($toMin, \App\Services\Timetable::LUNCH);
    $nowMin = now()->hour * 60 + now()->minute;
    $colW = $colW ?? '11rem';
@endphp
<div class="card overflow-auto p-0" style="max-height: 78vh" x-data="{ pop: null }" x-on:keydown.escape.window="pop = null">
    <div class="grid" style="grid-template-columns: 4.5rem repeat({{ count($cols) }}, minmax({{ $colW }}, 1fr)); min-width: calc(4.5rem + {{ count($cols) }} * {{ $colW }})">
        {{-- sticky header row --}}
        <div class="sticky left-0 top-0 z-30 h-12 border-b border-r border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900"></div>
        @foreach($cols as $key => $label)
            <div class="sticky top-0 z-20 flex h-12 items-center justify-between gap-2 border-b border-r border-slate-200 bg-white px-3 text-sm font-semibold last:border-r-0 dark:border-white/10 dark:bg-slate-900">
                @if(isset($colLink))<a href="{{ $colLink($key) }}" class="truncate hover:text-brand-700 hover:underline">{{ $label }}</a>@else<span class="truncate">{{ $label }}</span>@endif
                <span class="text-xs font-normal text-slate-500">{{ count($byCol[$key] ?? []) }}</span>
            </div>
        @endforeach

        {{-- sticky time column --}}
        <div class="sticky left-0 z-10 border-r border-slate-200 bg-white dark:border-white/10 dark:bg-slate-900" style="height: {{ ($endMin - $startMin) * $px }}px">
            <div class="relative h-full">
                @for($m = $startMin; $m < $endMin; $m += 30)
                    @if($m % 60 === 0)<div class="absolute right-2 -translate-y-1/2 whitespace-nowrap text-[11px] font-medium text-slate-500" style="top: {{ ($m - $startMin) * $px }}px">{{ ampm(sprintf('%02d:00', intdiv($m, 60))) }}</div>@endif
                @endfor
            </div>
        </div>

        @foreach($cols as $key => $label)
            <div class="relative border-r border-slate-100 last:border-r-0 dark:border-white/5" style="height: {{ ($endMin - $startMin) * $px }}px">
                @for($m = $startMin; $m < $endMin; $m += 30)
                    <div class="absolute inset-x-0 border-t {{ $m % 60 === 0 ? 'border-slate-200 dark:border-white/10' : 'border-dashed border-slate-100 dark:border-white/5' }}" style="top: {{ ($m - $startMin) * $px }}px"></div>
                @endfor
                <div class="tt-lunch absolute inset-x-0 flex items-center justify-center text-[11px] font-medium uppercase tracking-wider text-slate-500" style="top: {{ ($lunch[0] - $startMin) * $px }}px; height: {{ ($lunch[1] - $lunch[0]) * $px }}px">Lunch break</div>
                @if(in_array($key, $nowCols ?? [], true) && $nowMin >= $startMin && $nowMin <= $endMin)
                    <div class="pointer-events-none absolute inset-x-0 z-[5] border-t-2 border-rose-600" style="top: {{ ($nowMin - $startMin) * $px }}px" aria-label="Now"><span class="absolute -left-1 -top-[5px] h-2 w-2 rounded-full bg-rose-600"></span></div>
                @endif
                @foreach($byCol[$key] ?? [] as $s)
                    @php
                        $cs = $s->classSubject;
                        $top = ($toMin($s->starts_at) - $startMin) * $px + 2;
                        $h = max(24, ($toMin($s->ends_at) - $toMin($s->starts_at)) * $px - 4);
                        $hue = crc32($cs->subject->name) % 360;
                        $info = ['subject' => $cs->subject->name, 'section' => $cs->section->label(), 'teacher' => $cs->teacher?->name ?? 'No teacher yet', 'room' => $s->room ?? '—', 'time' => \App\Models\ScheduleSlot::DAYS[$s->day_of_week].' '.ampm($s->starts_at).' – '.ampm($s->ends_at)];
                    @endphp
                    <button type="button" class="subj absolute inset-x-1.5 overflow-hidden rounded-lg px-2.5 py-1.5 text-left text-xs leading-snug shadow-sm hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            style="--h: {{ $hue }}; top: {{ $top }}px; height: {{ $h }}px"
                            x-on:click="pop = (pop && pop.id === {{ $s->id }}) ? null : { id: {{ $s->id }}, ...@js($info) }" title="{{ implode(' · ', $info) }}" aria-label="{{ implode(', ', $info) }}">
                        <span class="block truncate font-semibold">{{ $cs->subject->name }}</span>
                        <span class="block truncate opacity-80">{{ $line2($s) }}</span>
                        @if($h > 60)<span class="block truncate opacity-70">{{ ampm($s->starts_at) }} – {{ ampm($s->ends_at) }}</span>@endif
                    </button>
                @endforeach
            </div>
        @endforeach
    </div>

    {{-- details popover --}}
    <div x-show="pop" x-cloak x-transition class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-sm rounded-2xl bg-white p-4 shadow-2xl ring-1 ring-slate-900/10 sm:inset-x-auto sm:right-6 dark:bg-slate-900 dark:ring-white/10" role="dialog" aria-label="Class details" x-on:click.outside="pop = null">
        <div class="flex items-start justify-between gap-3">
            <p class="text-base font-semibold" x-text="pop?.subject"></p>
            <button type="button" class="-m-2 flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" x-on:click="pop = null" aria-label="Close"><x-icon name="x" class="h-4 w-4" /></button>
        </div>
        <dl class="mt-1 grid grid-cols-[5rem_1fr] gap-y-1.5 text-sm">
            <dt class="text-slate-500">Time</dt><dd x-text="pop?.time"></dd>
            <dt class="text-slate-500">Section</dt><dd x-text="pop?.section"></dd>
            <dt class="text-slate-500">Teacher</dt><dd x-text="pop?.teacher"></dd>
            <dt class="text-slate-500">Room</dt><dd x-text="pop?.room"></dd>
        </dl>
    </div>
</div>
