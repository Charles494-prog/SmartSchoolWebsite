<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Payment slip #{{ $o->id }}</title>
<style nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    body { font-family: system-ui, sans-serif; color: #000; max-width: 380px; margin: 16px auto; font-size: 14px; }
    h1 { font-size: 18px; margin: 0 } table { width: 100%; border-collapse: collapse; margin-top: 10px } td, th { padding: 4px 0; border-bottom: 1px dashed #999; text-align: left } .r { text-align: right }
    .big { font-size: 20px; font-weight: 700 } .box { border: 2px solid #000; padding: 8px; margin-top: 12px; text-align: center; font-weight: 700 }
    button { min-height: 44px; padding: 0 16px; margin-top: 12px } @media print { button { display: none } }
</style></head>
<body>
    <h1>{{ \App\Models\Setting::get('school_name') }}</h1>
    <p>Bookstore – payment slip<br>{{ now()->format('M j, Y') }} {{ ampm(now()->format('H:i')) }}</p>
    <p><b>Order #{{ $o->id }}</b><br>{{ $o->student->full_name }} · ID {{ $o->student->student_no }}<br>{{ $e ? $e->gradeLevel->name.($e->section ? ' – '.$e->section->name : '') : '' }}</p>
    <table><thead><tr><th>Item</th><th class="r">Qty</th><th class="r">Amount</th></tr></thead><tbody>
        @foreach($o->lines as $l)<tr><td>{{ $l->name }}{{ $l->size ? ' ('.$l->size.')' : '' }}</td><td class="r">{{ $l->qty }}</td><td class="r">{{ number_format($l->price * $l->qty, 2) }}</td></tr>@endforeach
    </tbody></table>
    <p class="r big">Total ₱{{ number_format($o->total, 2) }}</p>
    <div class="box">PLEASE PAY AT THE FINANCE OFFICE<br>then bring the official receipt to the bookstore</div>
    <button type="button" id="p">Print</button>
    <script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">document.getElementById('p').addEventListener('click', () => window.print());</script>
</body></html>
