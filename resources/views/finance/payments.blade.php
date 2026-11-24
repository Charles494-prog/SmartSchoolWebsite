<x-layouts.app title="Payments">
    <form class="mb-4 flex flex-wrap items-end gap-2">
        <h1 class="h1 mr-auto">Payments</h1>
        <x-field name="from" label="From" type="date" :value="$from" />
        <x-field name="to" label="To" type="date" :value="$to" />
        <button class="btn">Show</button>
        <button type="button" onclick="window.print()" class="btn">Print</button>
    </form>
    <p class="mb-3 font-semibold">Total collected: {{ \App\Services\Billing::peso($total) }} ({{ $payments->where('is_void', false)->count() }} receipts)</p>
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Date</th><th>OR no.</th><th>Student</th><th>Method</th><th class="text-right">Amount</th><th>Cashier</th></tr></thead>
            <tbody>
            @forelse($payments as $p)
                <tr class="{{ $p->is_void ? 'text-slate-400 line-through' : '' }}">
                    <td>{{ $p->paid_on->format('M d, Y') }}</td><td><a class="link" target="_blank" href="{{ route('finance.receipt', $p) }}">{{ $p->or_number }}</a></td>
                    <td>{{ $p->account->enrollment->student->full_name }}</td><td>{{ \App\Models\Payment::METHODS[$p->method] }}</td>
                    <td class="text-right">{{ number_format($p->amount, 2) }}</td><td>{{ $p->receiver?->name }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No payments in this period.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
