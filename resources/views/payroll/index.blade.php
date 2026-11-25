<x-layouts.app title="Payroll">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="h1">Payroll</h1>
        <a href="{{ route('payroll.rates') }}" class="btn min-h-11"><x-icon name="settings" class="h-4 w-4" /> Rates (SSS, PhilHealth, Pag-IBIG, tax)</a>
    </div>
    <div class="grid gap-4 lg:grid-cols-3">
        <div class="card overflow-x-auto p-0 lg:col-span-2">
            <table class="table">
                <thead><tr><th>Period</th><th>Employees</th><th class="text-right">Total net pay</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($payrolls as $p)
                    <tr><td><a class="link" href="{{ route('payroll.show', $p) }}">{{ $p->period_start->format('M d') }} – {{ $p->period_end->format('M d, Y') }}</a></td><td>{{ $p->items_count }}</td><td class="text-right">{{ number_format($p->items_sum_net_pay, 2) }}</td><td><x-status :value="$p->status" /></td></tr>
                @empty
                    <tr><td colspan="4"><x-empty icon="banknote" title="No payroll yet" text="Payroll is made from your active employees and their monthly salary."
                        action="Add employees" :href="route('crud.index', 'employees')"
                        :steps="['Add each employee with their monthly salary in <b>Employees</b>.', 'Check the <b>Rates</b> (SSS, PhilHealth, Pag-IBIG, tax) are current.', 'Pick the period on the right and click <b>Generate</b>.', 'Review each line, then <b>Finalize</b> and print payslips.']" /></td></tr>
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
                <p class="text-xs text-slate-500">Periods of 16 days or less are computed as semi-monthly (half). SSS, PhilHealth, Pag-IBIG and withholding tax are computed from your <a class="link" href="{{ route('payroll.rates') }}">payroll rates</a> – review before finalizing.</p>
                <button class="btn btn-primary w-full">Generate</button>
            </form>
        </div>
    </div>
</x-layouts.app>
