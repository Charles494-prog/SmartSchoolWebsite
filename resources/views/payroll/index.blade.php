@php $peso = fn ($v) => '₱'.number_format((float) $v, 2); @endphp
<x-layouts.app title="Payroll">
    <x-page-header title="Payroll" subtitle="Pick a pay period to review it, or run payroll for a new period.">
        <a href="{{ route('payroll.rates') }}" class="btn min-h-11"><x-icon name="settings" class="h-4 w-4" /> Rates</a>
        <a href="{{ route('crud.index', 'employees') }}" class="btn min-h-11"><x-icon name="users" class="h-4 w-4" /> Employees</a>
    </x-page-header>

    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section>
            <div class="mb-3 flex flex-wrap items-center gap-2" x-data="{ q: '' }">
                @if($payrolls->isNotEmpty())
                    <label class="sr-only" for="period">Pay period</label>
                    <select id="period" class="input min-h-11 w-auto font-medium" x-on:change="if ($el.value) location = $el.value">
                        <option value="">Open a pay period…</option>
                        @foreach($payrolls as $p)<option value="{{ route('payroll.show', $p) }}">{{ $p->period_start->format('M j') }} – {{ $p->period_end->format('M j, Y') }}</option>@endforeach
                    </select>
                @endif
            </div>
            @forelse($payrolls as $p)
                <a href="{{ route('payroll.show', $p) }}" class="card mb-2 flex flex-wrap items-center gap-3 hover:ring-2 hover:ring-brand-500">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300"><x-icon name="banknote" class="h-5 w-5" /></span>
                    <span class="min-w-0 flex-1"><span class="block font-semibold">{{ $p->period_start->format('M j') }} – {{ $p->period_end->format('M j, Y') }}</span><span class="text-sm text-slate-600 dark:text-slate-400">{{ $p->items_count }} {{ str('employee')->plural($p->items_count) }}</span></span>
                    <span class="text-right"><span class="block text-lg font-bold tabular-nums">{{ $peso($p->items_sum_net_pay) }}</span><span class="text-xs text-slate-500">net pay</span></span>
                    @include('payroll._chip', ['status' => $p->status])
                </a>
            @empty
                <div class="card"><x-empty icon="banknote" title="No payroll yet" text="Payroll is made from your active employees and their monthly salary." action="Add employees" :href="route('crud.index', 'employees')"
                    :steps="['Add each employee with their monthly salary in <b>Employees</b>.', 'Check the <b>Rates</b> (SSS, PhilHealth, Pag-IBIG, tax) are current.', 'Pick the period on the right and click <b>Run payroll</b>.', 'Review, adjust, approve, then generate payslips.']" /></div>
            @endforelse
        </section>
        <form method="post" action="{{ route('payroll.store') }}" class="card h-fit space-y-3" x-data="{ busy: false }" x-on:submit="busy = true">@csrf
            <h2 class="h2">Run payroll</h2>
            <ol class="flex gap-1 text-[11px] font-medium text-slate-500">@foreach(['Period', 'Review', 'Adjust', 'Approve', 'Payslips'] as $n => $s)<li class="flex-1"><span class="mb-1 block h-1.5 rounded-full {{ $n === 0 ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700' }}"></span>{{ $s }}</li>@endforeach</ol>
            <x-field name="period_start" label="Period start" type="date" :value="now()->startOfMonth()->toDateString()" required />
            <x-field name="period_end" label="Period end" type="date" :value="now()->endOfMonth()->toDateString()" required />
            <p class="text-xs text-slate-600 dark:text-slate-400">16 days or less is computed as semi-monthly (half). Deductions use your <a class="link" href="{{ route('payroll.rates') }}">payroll rates</a>.</p>
            <button class="btn btn-primary min-h-11 w-full" :disabled="busy" x-text="busy ? 'Computing…' : 'Next: review'">Next: review</button>
        </form>
    </div>
</x-layouts.app>
