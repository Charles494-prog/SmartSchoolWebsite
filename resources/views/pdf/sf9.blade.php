<html><head>@include('pdf._head')</head><body>
@include('pdf._school', ['docTitle' => 'REPORT CARD – School year '.$e->schoolYear->name])
<table style="margin-bottom:6px"><tr>
    <td>Name: <b>{{ $e->student->full_name }}</b></td><td>LRN: <b>{{ $e->student->lrn }}</b></td>
    <td>Grade & Section: <b>{{ $e->gradeLevel->name }} – {{ $e->section?->name }}</b></td><td>Sex: {{ $e->student->sex }} · Age: {{ $e->student->age }}</td>
</tr></table>
<table><tr>
<td style="width:62%;vertical-align:top;padding-right:10px">
    <h2>Grades</h2>
    <table class="grid">
        <tr><th>Subject</th>@foreach(range(1, $e->gradeLevel->terms) as $t)<th class="c">{{ $t }}</th>@endforeach<th class="c">Final</th><th class="c">Remarks</th></tr>
        @foreach($rc['subjects'] as $s)
            <tr><td>{{ $s['name'] }}</td>@foreach($s['terms'] as $g)<td class="c">{{ $g }}</td>@endforeach<td class="c b">{{ $s['final'] }}</td><td class="c">{{ $s['remark'] }}</td></tr>
        @endforeach
        <tr><td class="b" colspan="{{ $e->gradeLevel->terms + 1 }}">General Average</td><td class="c b">{{ $rc['general_average'] }}</td><td class="c">{{ $rc['general_average'] !== null ? ($rc['general_average'] >= (float) \App\Models\Setting::get('passing_grade') ? 'Promoted' : 'Retained') : '' }}</td></tr>
    </table>
    @if($rc['honor'])<p class="b">{{ $rc['honor'] }}</p>@endif
    <table class="grid" style="margin-top:8px;width:70%">
        <tr><th>Descriptor</th><th>Grading scale</th><th>Remarks</th></tr>
        <tr><td>Outstanding</td><td class="c">90–100</td><td class="c">Passed</td></tr>
        <tr><td>Very Satisfactory</td><td class="c">85–89</td><td class="c">Passed</td></tr>
        <tr><td>Satisfactory</td><td class="c">80–84</td><td class="c">Passed</td></tr>
        <tr><td>Fairly Satisfactory</td><td class="c">75–79</td><td class="c">Passed</td></tr>
        <tr><td>Did Not Meet Expectations</td><td class="c">Below 75</td><td class="c">Failed</td></tr>
    </table>
</td>
<td style="vertical-align:top">
    <h2>Attendance</h2>
    <table class="grid">
        <tr><th>Month</th><th class="c">Days present</th><th class="c">Days absent</th><th class="c">Times late</th></tr>
        @php $tp = $ta = $tl = 0; @endphp
        @foreach($attendance as $month => $rows)
            @php $p = $rows->whereIn('status', ['present', 'late'])->count(); $a = $rows->where('status', 'absent')->count(); $l = $rows->where('status', 'late')->count(); $tp += $p; $ta += $a; $tl += $l; @endphp
            <tr><td>{{ \Carbon\Carbon::parse($month.'-01')->format('M Y') }}</td><td class="c">{{ $p }}</td><td class="c">{{ $a }}</td><td class="c">{{ $l }}</td></tr>
        @endforeach
        <tr><td class="b">Total</td><td class="c b">{{ $tp }}</td><td class="c b">{{ $ta }}</td><td class="c b">{{ $tl }}</td></tr>
    </table>
    <br><br>
    <table><tr>
        <td class="c">______________________<br><b>{{ $e->section?->adviser?->name }}</b><br>Adviser</td>
        <td class="c">______________________<br><b>{{ \App\Models\Setting::get('school_head') }}</b><br>School Head</td>
    </tr></table>
    <br><p class="muted">Parent/Guardian signature: ________________________</p>
</td>
</tr></table>
</body></html>
