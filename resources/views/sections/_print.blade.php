{{-- Print sheets: one room / section / teacher per landscape page, bordered week grid. $sheets: [title => slots] --}}
@php
    $days = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday'];
    $rowsFor = function ($slots) {
        $t = collect(\App\Services\Timetable::PERIODS)->map(fn ($p) => $p[0].'|'.$p[1])
            ->merge($slots->map(fn ($s) => substr($s->starts_at, 0, 5).'|'.substr($s->ends_at, 0, 5)))->unique()->sort()->values();
        return $t;
    };
@endphp
@foreach($sheets as $sheetTitle => $slots)
    @php $hasSat = $slots->contains('day_of_week', 6); $cols = $hasSat ? $days + [6 => 'Saturday'] : $days; @endphp
    <section class="print-sheet">
        <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:6px;color:#000">
            <div><div style="font-size:15px;font-weight:700">{{ $school }}</div><div style="font-size:11px">School year {{ $year }} · Class schedule</div></div>
            <div style="font-size:18px;font-weight:700">{{ $sheetTitle }}</div>
        </div>
        <table class="print-grid">
            <thead><tr><th style="width:11%">Time</th>@foreach($cols as $d)<th>{{ $d }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach($rowsFor($slots) as $i => $t)
                    @php [$st, $en] = explode('|', $t); @endphp
                    @if($st === \App\Services\Timetable::LUNCH[1] && $i > 0)
                        <tr><td><b>{{ ampm(\App\Services\Timetable::LUNCH[0]) }} – {{ ampm(\App\Services\Timetable::LUNCH[1]) }}</b></td><td colspan="{{ count($cols) }}" style="text-align:center;background:#f3f3f3">LUNCH BREAK</td></tr>
                    @endif
                    <tr>
                        <td><b>{{ ampm($st) }}</b><br>{{ ampm($en) }}</td>
                        @foreach($cols as $dn => $d)
                            <td>
                                @foreach($slots->filter(fn ($s) => $s->day_of_week == $dn && substr($s->starts_at, 0, 5) === $st && substr($s->ends_at, 0, 5) === $en) as $s)
                                    <div><b>{{ $s->classSubject->subject->name }}</b></div>
                                    <div>{{ $s->classSubject->section->label() }}</div>
                                    <div>{{ $s->classSubject->teacher?->name ?? 'No teacher' }} · {{ $s->room }}</div>
                                @endforeach
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
@endforeach
