<x-layouts.app :ajaxForms="true" title="Transactions">
    @php $peso = fn ($v) => \App\Services\Billing::peso($v); $q = request('q'); @endphp
    <div class="mb-5 flex flex-wrap items-end gap-3">
        <div class="mr-auto"><h1 class="h1">Transactions</h1><p class="muted">Find a student to take a payment, print a statement or set up their payment plan.</p></div>
        @if($pendingOnline)<a href="{{ route('finance.proofs') }}" class="btn"><span class="h-2 w-2 rounded-full bg-amber-500"></span> {{ $pendingOnline }} online {{ str('payment')->plural($pendingOnline) }} to check</a>@endif
    </div>

    {{-- Start here --}}
    <form class="mb-5 rounded-2xl border-2 border-brand-200 bg-brand-50/50 p-5 dark:border-brand-500/30 dark:bg-brand-500/5">
        <input type="hidden" name="view" value="{{ $view }}"><input type="hidden" name="filter" value="{{ $filter }}">
        <label for="tx-search" class="text-sm font-semibold text-brand-900 dark:text-brand-200">New transaction – who is paying?</label>
        <div class="mt-2 flex gap-2">
            <div class="relative flex-1"><x-icon name="search" class="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 text-slate-400" />
                <input id="tx-search" name="q" value="{{ $q }}" class="input h-12 pl-10 text-base" placeholder="Type the student's name, student no. or LRN" autofocus autocomplete="off"></div>
            <button class="btn btn-primary h-12 px-6">Find</button>
        </div>
    </form>

    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <x-stat label="Billed this school year" :value="$peso($billed)" />
        <x-stat label="Collected" :value="$peso($collected)" tone="green" />
        <x-stat label="Collection rate" :value="$rate.'%'" tone="brand" />
        <x-stat label="Overdue" :value="$peso($overdueTotal)" tone="red" :href="route('finance.index', ['filter' => 'overdue'])" />
    </div>

    {{-- Tabs + filters --}}
    <div class="mb-3 flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
            <a href="{{ route('finance.index', ['q' => $q]) }}" class="rounded-lg px-3 py-1.5 font-medium {{ $view === 'balances' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">Balances</a>
            <a href="{{ route('finance.index', ['view' => 'receipts', 'q' => $q]) }}" class="rounded-lg px-3 py-1.5 font-medium {{ $view === 'receipts' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">Receipts</a>
        </div>
        @if($view === 'balances')
            @foreach(['balance' => 'With balance', 'overdue' => 'Overdue', 'unassessed' => 'Fees not set', 'settled' => 'Settled', 'all' => 'Everyone'] as $k => $label)
                <a href="{{ route('finance.index', ['filter' => $k, 'q' => $q]) }}" class="rounded-full border px-3 py-1 text-sm {{ $filter === $k ? 'border-slate-900 bg-slate-900 text-white dark:border-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">{{ $label }}@isset($counts[$k]) <span class="opacity-60">{{ $counts[$k] }}</span>@endisset</a>
            @endforeach
            @if($filter === 'overdue' && $rows->isNotEmpty())<a href="{{ route('finance.overdue') }}" class="ml-auto text-sm text-brand-600 hover:underline">Send reminders…</a>@endif
        @endif
    </div>

    @if($view === 'balances')
        <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                @forelse($rows as $e)
                    @php $a = $e->account; $bal = $a?->balance(); $od = $a?->overdueAmount() ?? 0; @endphp
                    <li><a href="{{ route('finance.show', $e) }}" class="flex flex-wrap items-center gap-4 px-5 py-3.5 hover:bg-slate-50 dark:hover:bg-white/5">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ strtoupper(substr($e->student->first_name, 0, 1).substr($e->student->last_name, 0, 1)) }}</span>
                        <span class="min-w-0 flex-1"><span class="block font-medium">{{ $e->student->full_name }}</span><span class="text-xs text-slate-500">{{ $e->gradeLevel->name }} {{ $e->section?->name }} · {{ $a?->assessed_at ? $a->planName() : 'fees not set yet' }}</span></span>
                        @if($od > 0)<span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-700">Overdue {{ $peso($od) }}</span>@endif
                        <span class="w-32 text-right">
                            @if($a && $bal > 0)<span class="block text-lg font-bold tabular-nums">{{ $peso($bal) }}</span><span class="text-xs text-slate-500">{{ $a->assessed_at ? 'balance' : 'orders & documents' }}</span>
                            @elseif(! $a?->assessed_at)<span class="text-sm font-medium text-amber-600">Set fees →</span>
                            @elseif($bal <= 0)<span class="text-sm font-semibold text-emerald-600">Settled</span>
                            @else<span class="block text-lg font-bold tabular-nums">{{ $peso($bal) }}</span><span class="text-xs text-slate-500">balance</span>@endif
                        </span>
                        <span class="hidden text-sm font-semibold text-brand-600 sm:block">{{ $a && $bal > 0 ? 'Take payment →' : 'Open →' }}</span>
                    </a></li>
                @empty
                    <li class="px-5 py-10 text-center text-slate-500">{{ $q ? 'No student matches “'.$q.'”.' : 'No one here.' }} @if($filter !== 'all')<a class="link" href="{{ route('finance.index', ['filter' => 'all', 'q' => $q]) }}">Show everyone</a>@endif</li>
                @endforelse
            </ul>
        </div>
        @if($filter === 'balance')<p class="mt-2 text-xs text-slate-500">Settled balances are hidden. Use the Settled filter to see them.</p>@endif
    @else
        <div class="card overflow-x-auto p-0">
            <table class="table">
                <thead><tr><th>Date</th><th>OR no.</th><th>Student</th><th>Method</th><th>Received by</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                @forelse($receipts as $p)
                    <tr class="{{ $p->is_void ? 'text-slate-400 line-through' : '' }}">
                        <td>{{ $p->paid_on->format('M d, Y') }}</td>
                        <td><a class="link" target="_blank" href="{{ route('finance.receipt', $p) }}">{{ $p->or_number }}</a></td>
                        <td>{{ $p->account?->enrollment?->student?->full_name }}</td>
                        <td>{{ \App\Models\Payment::METHODS[$p->method] ?? $p->method }}</td>
                        <td>{{ $p->receiver?->name }}</td>
                        <td class="text-right font-semibold tabular-nums">{{ number_format($p->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No receipts.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $receipts->links() }}</div>
    @endif
</x-layouts.app>
