<x-layouts.app title="Schedule">
    @include('portal._switch')
    @if($child)
        @php
            $days = collect([1, 2, 3, 4, 5, 6])->filter(fn ($d) => $d <= 5 || ($slots[$d] ?? collect())->isNotEmpty())->values();
            $times = $slots->flatten()->map(fn ($s) => substr($s->starts_at, 0, 5).'|'.substr($s->ends_at, 0, 5))->unique()->sort()->values();
            $palette = ['bg-indigo-50 border-indigo-400 text-indigo-900', 'bg-emerald-50 border-emerald-400 text-emerald-900', 'bg-sky-50 border-sky-400 text-sky-900', 'bg-amber-50 border-amber-400 text-amber-900', 'bg-rose-50 border-rose-400 text-rose-900', 'bg-fuchsia-50 border-fuchsia-400 text-fuchsia-900', 'bg-teal-50 border-teal-400 text-teal-900', 'bg-orange-50 border-orange-400 text-orange-900'];
            $color = fn ($s) => $palette[crc32($s->classSubject->subject->name) % count($palette)];
            $today = now()->dayOfWeekIso; $nowT = now()->format('H:i');
            $todaySlots = ($slots[$today] ?? collect());
            $current = $todaySlots->first(fn ($s) => substr($s->starts_at, 0, 5) <= $nowT && substr($s->ends_at, 0, 5) > $nowT);
            $next = $todaySlots->first(fn ($s) => substr($s->starts_at, 0, 5) > $nowT);
        @endphp

        <div class="mb-6 flex flex-wrap items-end gap-3">
            <div class="mr-auto">
                <h1 class="h1">Class schedule</h1>
                <p class="muted">{{ $enrollment?->section?->label() ?? 'No section yet' }}</p>
            </div>
            <button type="button" x-data x-on:click="window.print()" class="btn print:hidden">Print</button>
        </div>

        @if($slots->isEmpty())
            <p class="card muted">No schedule yet.</p>
        @else
            {{-- Right now --}}
            <div class="mb-6 grid gap-3 sm:grid-cols-2 print:hidden">
                <div class="rounded-2xl bg-slate-900 p-5 text-white dark:bg-slate-800">
                    <p class="text-xs font-semibold uppercase tracking-wider text-white/70">Now</p>
                    @if($current)
                        <p class="mt-1 text-2xl font-bold">{{ $current->classSubject->subject->name }}</p>
                        <p class="text-white/85">until {{ ampm(substr($current->ends_at, 0, 5)) }} · {{ $current->classSubject->teacher?->name }} · {{ $current->room }}</p>
                    @else
                        <p class="mt-1 text-2xl font-bold">No class right now</p>
                    @endif
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Next today</p>
                    @if($next)
                        <p class="mt-1 text-2xl font-bold">{{ $next->classSubject->subject->name }}</p>
                        <p class="text-slate-500">{{ ampm(substr($next->starts_at, 0, 5)) }} · {{ $next->classSubject->teacher?->name }} · {{ $next->room }}</p>
                    @else
                        <p class="mt-1 text-2xl font-bold text-slate-400">Done for today</p>
                    @endif
                </div>
            </div>

            {{-- Week grid --}}
            <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-slate-900">
                <table class="w-full table-fixed border-collapse text-sm">
                    <thead>
                        <tr>
                            <th class="w-28 border-b border-slate-200 p-3 text-left text-xs font-semibold text-slate-400 dark:border-slate-800">Time</th>
                            @foreach($days as $d)
                                <th class="border-b border-slate-200 p-3 text-left dark:border-slate-800 {{ $d === $today ? 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-300' : 'text-slate-700 dark:text-slate-200' }}">
                                    {{ \App\Models\ScheduleSlot::DAYS[$d] }} @if($d === $today)<span class="ml-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-bold text-white">Today</span>@endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($times as $t)
                            @php [$from, $to] = explode('|', $t); @endphp
                            <tr>
                                <td class="border-b border-slate-100 p-3 align-top text-xs font-medium text-slate-500 dark:border-slate-800">{{ ampm($from) }}<br><span class="text-slate-400">{{ ampm($to) }}</span></td>
                                @foreach($days as $d)
                                    @php $s = ($slots[$d] ?? collect())->first(fn ($x) => substr($x->starts_at, 0, 5) === $from); $isNow = $current && $s && $s->id === $current->id; @endphp
                                    <td class="border-b border-slate-100 p-1.5 align-top dark:border-slate-800 {{ $d === $today ? 'bg-brand-50/40 dark:bg-brand-500/5' : '' }}">
                                        @if($s)
                                            <div class="h-full rounded-xl border-l-4 p-2.5 {{ $color($s) }} {{ $isNow ? 'ring-2 ring-brand-500' : '' }}">
                                                <div class="font-semibold leading-tight">{{ $s->classSubject->subject->name }}</div>
                                                <div class="mt-0.5 truncate text-xs opacity-75">{{ $s->classSubject->teacher?->name }}</div>
                                                <div class="text-xs opacity-60">{{ $s->room }}</div>
                                            </div>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Phone: one day at a time --}}
            <div class="md:hidden" x-data="{ day: {{ in_array($today, $days->all()) ? $today : $days->first() }} }">
                <div class="mb-3 flex gap-1 overflow-x-auto">
                    @foreach($days as $d)
                        <button type="button" class="rounded-full px-3 py-1.5 text-sm font-semibold" :class="day === {{ $d }} ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 dark:bg-slate-900'" x-on:click="day = {{ $d }}">{{ substr(\App\Models\ScheduleSlot::DAYS[$d], 0, 3) }}</button>
                    @endforeach
                </div>
                @foreach($days as $d)
                    <div x-show="day === {{ $d }}" class="space-y-2">
                        @foreach($slots[$d] ?? [] as $s)
                            <div class="flex gap-3 rounded-xl border-l-4 p-3 {{ $color($s) }}">
                                <div class="w-16 shrink-0 text-xs font-semibold">{{ ampm(substr($s->starts_at, 0, 5)) }}</div>
                                <div><div class="font-semibold">{{ $s->classSubject->subject->name }}</div><div class="text-xs opacity-75">{{ $s->classSubject->teacher?->name }} · {{ $s->room }}</div></div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</x-layouts.app>
