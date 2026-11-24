<x-layouts.app title="Attendance report">
    <form class="mb-4 flex flex-wrap items-end gap-2">
        <h1 class="h1 mr-auto">Monthly attendance</h1>
        <select x-data name="section_id" class="input max-w-xs" x-on:change="$el.form.submit()">@foreach($sections as $k => $v)<option value="{{ $k }}" @selected($section?->id == $k)>{{ $v }}</option>@endforeach</select>
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="input w-44" x-data x-on:change="$el.form.submit()">
        <button x-data type="button" x-on:click="window.print()" class="btn">Print</button>
    </form>
    <p class="muted mb-2">P present · L late · A absent · E excused · blank = no record. Weekdays only.</p>
    <div class="card overflow-x-auto p-0">
        <table class="table text-xs">
            <thead><tr><th>Student</th>@foreach($days as $d)<th class="px-1 text-center">{{ $d->format('j') }}<br>{{ substr($d->format('D'), 0, 2) }}</th>@endforeach<th>P</th><th>L</th><th>A</th></tr></thead>
            <tbody>
            @foreach($students as $s)
                @php $mine = $records[$s->id] ?? collect(); @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ $s->full_name }}</td>
                    @foreach($days as $d)
                        @php $st = $mine[$d->format('Y-m-d')]->status ?? null; @endphp
                        <td class="px-1 text-center {{ $st === 'absent' ? 'bg-red-50 text-red-700 font-bold' : ($st === 'late' ? 'bg-amber-50 text-amber-700' : '') }}">{{ $st ? strtoupper($st[0]) : '' }}</td>
                    @endforeach
                    <td>{{ $mine->whereIn('status', ['present', 'late'])->count() }}</td><td>{{ $mine->where('status', 'late')->count() }}</td><td class="font-bold text-red-700">{{ $mine->where('status', 'absent')->count() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
