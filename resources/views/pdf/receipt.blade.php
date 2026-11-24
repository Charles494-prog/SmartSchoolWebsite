<html><head>@include('pdf._head')</head><body>
<div class="c"><h1>{{ \App\Models\Setting::get('school_name') }}</h1><div class="muted">{{ \App\Models\Setting::get('school_address') }}</div><div class="b" style="margin-top:6px">OFFICIAL RECEIPT</div></div>
<table style="margin-top:10px">
    <tr><td>OR No.:</td><td class="b">{{ $p->or_number }}</td><td class="r">Date: {{ $p->paid_on->format('M d, Y') }}</td></tr>
    <tr><td>Received from:</td><td colspan="2" class="b">{{ $a->enrollment->student->full_name }} ({{ $a->enrollment->student->student_no }})</td></tr>
    <tr><td>For:</td><td colspan="2">School fees · {{ $a->enrollment->gradeLevel->name }} · S.Y. {{ $a->enrollment->schoolYear->name }}</td></tr>
    <tr><td>Amount:</td><td colspan="2" class="b" style="font-size:14px">₱{{ number_format($p->amount, 2) }}</td></tr>
    <tr><td>Payment method:</td><td colspan="2">{{ \App\Models\Payment::METHODS[$p->method] }} {{ $p->reference }}</td></tr>
    <tr><td>Balance after payment:</td><td colspan="2">₱{{ number_format($a->net_amount - $a->payments()->where('is_void', false)->where('id', '<=', $p->id)->sum('amount'), 2) }}</td></tr>
</table>
@if($p->is_void)<p class="b" style="color:#b00;font-size:16px">VOID – {{ $p->void_reason }}</p>@endif
<br><br><table><tr><td></td><td class="c" style="width:50%">______________________<br>{{ $p->receiver?->name }}<br>Cashier</td></tr></table>
</body></html>
