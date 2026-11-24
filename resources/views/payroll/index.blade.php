<x-layouts.app title="Payroll">
    <h1 class="h1 mb-4">Payroll</h1>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card overflow-x-auto p-0 lg:col-span-2">
            <table class="table">
                <thead><tr><th>Period</th><th>Employees</th><th class="text-right">Total net pay</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($payrolls as $p)
                    <tr><td><a class="link" href="{{ route('payroll.show', $p) }}">{{ $p->period_start->format('M d') }} – {{ $p->period_end->format('M d, Y') }}</a></td><td>{{ $p->items_count }}</td><td class="text-right">{{ number_format($p->items_sum_net_pay, 2) }}</td><td><x-status :value="$p->status" /></td></tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-slate-500">No payroll yet. Add employees first (Services → Employees).</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card h-fit">
            <h2 class="h2 mb-3">Generate payroll</h2>
            <form method="post" action="{{ route('payroll.store') }}" class="space-y-3">
                @csrf
                <x-field name="period_start" label="Period start" type="date" :value="now()->startOfMonth()->toDateString()" required />
                <x-field name="period_end" label="Period end" type="date" :value="now()->endOfMonth()->toDateString()" required />
                <p class="text-xs text-slate-500">Periods of 16 days or less are computed as semi-monthly (half). SSS, PhilHealth, Pag-IBIG and withholding tax are estimates from the current rate tables – review before finalizing.</p>
                <button class="btn btn-primary w-full">Generate</button>
            </form>
        </div>
    </div>
</x-layouts.app>
