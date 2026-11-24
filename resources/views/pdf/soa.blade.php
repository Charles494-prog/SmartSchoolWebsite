<html><head>@include('pdf._head')</head><body>
@include('pdf._school', ['docTitle' => 'STATEMENT OF ACCOUNT'])
<p>Student: <b>{{ $e->student->full_name }}</b> ({{ $e->student->student_no }}) · {{ $e->gradeLevel->name }} {{ $e->section?->name }} · S.Y. {{ $e->schoolYear->name }} · As of {{ now()->format('M d, Y') }}</p>
<table class="grid">
    <tr><th>Fees</th><th class="r">Amount</th></tr>
    @foreach($fees as $f)<tr><td>{{ $f->name }}</td><td class="r">{{ number_format($f->amount, 2) }}</td></tr>@endforeach
    @foreach($a->discounts as $d)<tr><td>Less: {{ $d->discount->name }}</td><td class="r">({{ number_format($d->amount, 2) }})</td></tr>@endforeach
    <tr><td class="b">Net amount due</td><td class="r b">{{ number_format($a->net_amount, 2) }}</td></tr>
</table>
<h2>Payments</h2>
<table class="grid">
    <tr><th>Date</th><th>OR no.</th><th class="r">Amount</th></tr>
    @forelse($a->payments->where('is_void', false) as $p)<tr><td>{{ $p->paid_on->format('M d, Y') }}</td><td>{{ $p->or_number }}</td><td class="r">{{ number_format($p->amount, 2) }}</td></tr>@empty<tr><td colspan="3">None</td></tr>@endforelse
    <tr><td colspan="2" class="b">Total paid</td><td class="r b">{{ number_format($a->paidAmount(), 2) }}</td></tr>
</table>
<h2>Installment schedule</h2>
<table class="grid">
    <tr><th>Installment</th><th>Due date</th><th class="r">Amount</th></tr>
    @foreach($a->installments as $i)<tr><td>{{ $i->label }}</td><td>{{ $i->due_on->format('M d, Y') }}</td><td class="r">{{ number_format($i->amount, 2) }}</td></tr>@endforeach
</table>
<p style="font-size:14px" class="b">Remaining balance: ₱{{ number_format($a->balance(), 2) }} @if($a->overdueAmount() > 0) · Overdue: ₱{{ number_format($a->overdueAmount(), 2) }} @endif</p>
</body></html>
