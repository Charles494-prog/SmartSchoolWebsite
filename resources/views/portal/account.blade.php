<x-layouts.app title="Statement of account">
    @include('portal._switch')
    @if($child)
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <h1 class="h1 flex-1">Statement of account · {{ $child->first_name }}</h1>
            @if($a)<a href="{{ route('finance.soa', $enrollment) }}" target="_blank" class="btn">Download PDF</a>@endif
        </div>
        @if(! $a)
            <p class="card muted">Fees have not been assessed yet. Please visit the cashier.</p>
        @else
            <div class="grid gap-3 sm:grid-cols-3">
                <x-stat label="Total due" :value="\App\Services\Billing::peso($a->net_amount)" />
                <x-stat label="Paid" :value="\App\Services\Billing::peso($a->paidAmount())" tone="green" />
                <x-stat label="Balance" :value="\App\Services\Billing::peso($a->balance())" :tone="$a->balance() > 0 ? 'red' : 'green'" />
            </div>
            @if($a->overdueAmount() > 0)<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">Overdue: {{ \App\Services\Billing::peso($a->overdueAmount()) }}. Please settle at the cashier.</p>@endif
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="card"><h2 class="h2 mb-2">Installments</h2>@foreach($a->installments as $i)<p class="flex justify-between text-sm"><span>{{ $i->label }} · due {{ $i->due_on->format('M d, Y') }}</span><span>{{ number_format($i->amount, 2) }}</span></p>@endforeach</div>
                <div class="card"><h2 class="h2 mb-2">Payments</h2>@forelse($a->payments->where('is_void', false) as $p)<p class="flex justify-between text-sm"><span>{{ $p->paid_on->format('M d, Y') }} · <a class="link" target="_blank" href="{{ route('finance.receipt', $p) }}">{{ $p->or_number }}</a></span><span>{{ number_format($p->amount, 2) }}</span></p>@empty<p class="muted">None yet.</p>@endforelse</div>
            </div>
        @endif
    @endif
</x-layouts.app>
