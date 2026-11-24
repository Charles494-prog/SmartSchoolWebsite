<html><head>@include('pdf._head')</head><body>
@foreach($p->items as $i)
    <div style="border:1px solid #555;padding:10px;margin-bottom:12px;page-break-inside:avoid">
        <div class="c b">{{ \App\Models\Setting::get('school_name') }} – PAYSLIP</div>
        <div class="c muted">Period {{ $p->period_start->format('M d') }} – {{ $p->period_end->format('M d, Y') }}</div>
        <p><b>{{ $i->employee->full_name }}</b> ({{ $i->employee->employee_no }}) · {{ $i->employee->position }}</p>
        <table class="grid">
            <tr><th>Earnings</th><th class="r">Amount</th><th>Deductions</th><th class="r">Amount</th></tr>
            <tr><td>Basic pay</td><td class="r">{{ number_format($i->basic_pay, 2) }}</td><td>SSS</td><td class="r">{{ number_format($i->sss, 2) }}</td></tr>
            <tr><td>Allowances</td><td class="r">{{ number_format($i->allowances, 2) }}</td><td>PhilHealth</td><td class="r">{{ number_format($i->philhealth, 2) }}</td></tr>
            <tr><td></td><td></td><td>Pag-IBIG</td><td class="r">{{ number_format($i->pagibig, 2) }}</td></tr>
            <tr><td></td><td></td><td>Withholding tax</td><td class="r">{{ number_format($i->withholding_tax, 2) }}</td></tr>
            <tr><td></td><td></td><td>Other</td><td class="r">{{ number_format($i->other_deductions, 2) }}</td></tr>
            <tr><td class="b">NET PAY</td><td class="r b" colspan="3">₱{{ number_format($i->net_pay, 2) }}</td></tr>
        </table>
        <p class="muted">Received by: ____________________</p>
    </div>
@endforeach
</body></html>
