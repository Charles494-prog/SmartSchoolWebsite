<x-layouts.app title="Grades">
    @include('portal._switch')
    @if($child)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="h1 flex-1">Grades · {{ $child->first_name }}</h1>
            @if($enrollment)<a href="{{ route('students.sf9', $enrollment) }}" target="_blank" class="btn">Download report card</a>@endif
        </div>
        @if($report && count($report['subjects']))
            <div class="card overflow-x-auto p-0">
                <table class="table">
                    <thead><tr><th>Subject</th>@foreach(range(1, $enrollment->gradeLevel->terms) as $t)<th class="text-center">{{ \App\Support\School::term($t, $enrollment->gradeLevel->terms) }}</th>@endforeach<th class="text-center">Final</th><th>Remarks</th></tr></thead>
                    <tbody>
                    @foreach($report['subjects'] as $s)
                        <tr><td>{{ $s['name'] }}</td>@foreach($s['terms'] as $g)<td class="text-center {{ $g !== null && $g < 75 ? 'text-red-600 font-semibold' : '' }}">{{ $g ?? '' }}</td>@endforeach<td class="text-center font-bold">{{ $s['final'] }}</td><td>{{ $s['remark'] }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 font-semibold">General average: {{ $report['general_average'] ?? 'not complete yet' }} @if($report['honor'])<span class="badge ml-2 bg-amber-100 text-amber-800"><x-icon name="award" class="h-3.5 w-3.5" /> {{ $report['honor'] }}</span>@endif</p>
            <p class="muted">Grades appear as teachers record them; final grades are complete after the last grading period.</p>
        @else
            <p class="card muted">No grades yet this school year.</p>
        @endif
        <h2 class="h2 mt-6 mb-2">Previous school years</h2>
        <ul class="text-sm">@foreach($history as $h)<li>{{ $h->schoolYear->name }} · {{ $h->gradeLevel->name }} · <a class="link" target="_blank" href="{{ route('students.sf9', $h) }}">report card</a></li>@endforeach</ul>
    @endif
</x-layouts.app>
