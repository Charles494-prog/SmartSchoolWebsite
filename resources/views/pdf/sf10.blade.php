<html><head>@include('pdf._head')</head><body>
@include('pdf._school', ['docTitle' => 'PERMANENT ACADEMIC RECORD'])
<h2>Student information</h2>
<table class="grid">
    <tr><td>Last name: <b>{{ $s->last_name }}</b></td><td>First name: <b>{{ $s->first_name }}</b></td><td>Middle name: <b>{{ $s->middle_name }}</b></td><td>Suffix: {{ $s->suffix }}</td></tr>
    <tr><td>LRN: <b>{{ $s->lrn }}</b></td><td>Birthdate: {{ $s->birthdate->format('m/d/Y') }}</td><td>Sex: {{ $s->sex }}</td><td>School ID: {{ $s->student_no }}</td></tr>
    <tr><td colspan="2">Address: {{ $s->address }}</td><td colspan="2">Parent/Guardian: {{ $s->guardians->pluck('name')->implode(', ') }}</td></tr>
</table>
@foreach($records as $rec)
    @php $e = $rec['e']; $rc = $rec['rc']; @endphp
    <h2>Scholastic record – {{ $e->gradeLevel->name }} · S.Y. {{ $e->schoolYear->name }} · Section {{ $e->section?->name }} · Adviser {{ $e->section?->adviser?->name }}</h2>
    <table class="grid">
        <tr><th>Subject</th>@foreach(range(1, $e->gradeLevel->terms) as $t)<th class="c">{{ $t }}</th>@endforeach<th class="c">Final rating</th><th class="c">Remarks</th></tr>
        @forelse($rc['subjects'] as $sub)
            <tr><td>{{ $sub['name'] }}</td>@foreach($sub['terms'] as $g)<td class="c">{{ $g }}</td>@endforeach<td class="c b">{{ $sub['final'] }}</td><td class="c">{{ $sub['remark'] }}</td></tr>
        @empty
            <tr><td colspan="9" class="muted">No grades recorded.</td></tr>
        @endforelse
        <tr><td class="b" colspan="{{ $e->gradeLevel->terms + 1 }}">General average</td><td class="c b">{{ $rc['general_average'] }}</td><td class="c">{{ ucwords(str_replace('_', ' ', $e->status)) }}</td></tr>
    </table>
@endforeach
@foreach($s->transfers()->get() as $t)
    <p>Transferred {{ $t->direction === 'in' ? 'in from' : 'out to' }} {{ $t->school_name }} on {{ $t->transfer_date->format('M d, Y') }}.</p>
@endforeach
<br><br>
<table><tr>
    <td class="c">______________________<br>Registrar</td>
    <td class="c">______________________<br><b>{{ \App\Models\Setting::get('school_head') }}</b><br>School Head</td>
</tr></table>
<p class="muted">Printed {{ now()->format('M d, Y g:i A') }}. Not valid without the school seal.</p>
</body></html>
