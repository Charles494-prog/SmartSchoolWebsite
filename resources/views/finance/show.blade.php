@php
    $peso = fn ($v) => \App\Services\Billing::peso((float) $v);
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-layouts.app :title="'Account · '.$e->student->full_name" :hideErrorSummary="true">
    <x-page-header :title="$e->student->full_name" :back="route('finance.index')" backLabel="Student balances"
                   :subtitle="$e->gradeLevel->name.($e->section ? ' – '.$e->section->name : '').' · S.Y. '.$e->schoolYear->name.' · School ID '.$e->student->student_no">
        @if($a)<a href="{{ route('finance.soa', $e) }}" target="_blank" class="btn"><x-icon name="file" class="h-4 w-4" /> Statement (PDF)</a>@endif
    </x-page-header>

    @if(session('receipt'))
        <div class="mb-6 flex items-center gap-3 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200">
            <x-icon name="check-circle" class="h-5 w-5" /> <span class="flex-1">Payment saved.</span>
            <a class="btn btn-sm" target="_blank" href="{{ route('finance.receipt', session('receipt')) }}"><x-icon name="receipt" class="h-4 w-4" /> Print receipt</a>
        </div>
    @endif

    @if(! $a)
        <div class="card max-w-xl">
            <h2 class="h2">Set up this student's fees</h2>
            <p class="muted mt-1">Choose how they'll pay and any discount or scholarship.</p>
            @if($fees->isEmpty())<p class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900">No fees are set for {{ $e->gradeLevel->name }} yet. <a class="link" href="{{ route('crud.index', 'fee-items') }}">Add fees</a> first.</p>@endif
            @include('finance._assess-form')
        </div>
    @else
        @php $pct = $a->net_amount > 0 ? min(100, round($paid / $a->net_amount * 100)) : 100; $overdue = $a->overdueAmount(); @endphp
        {{-- Summary --}}
        <div class="card mb-6">
            <div class="grid gap-6 sm:grid-cols-3">
                <div><div class="muted">Total due</div><div class="mt-1 text-2xl font-bold">{{ $peso($a->net_amount) }}</div><div class="text-xs text-slate-500">{{ $a->planName() }}</div></div>
                <div><div class="muted">Paid</div><div class="mt-1 text-2xl font-bold text-emerald-700">{{ $peso($paid) }}</div><div class="text-xs text-slate-500">{{ $pct }}% paid</div></div>
                <div><div class="muted">Balance</div><div class="mt-1 text-2xl font-bold {{ $a->balance() > 0 ? 'text-slate-900' : 'text-emerald-700' }}">{{ $peso($a->balance()) }}</div>
                    @if($overdue > 0)<div class="text-xs font-semibold text-rose-600">{{ $peso($overdue) }} overdue</div>@else<div class="text-xs text-slate-500">Nothing overdue</div>@endif</div>
            </div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-2 rounded-full bg-emerald-500" style="width: {{ $pct }}%"></div></div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="space-y-6">
                {{-- Schedule --}}
                <div class="card" x-data="{ edit: {{ session('error') && old('rows') ? 'true' : 'false' }} }">
                    <div class="mb-4 flex items-center gap-3"><h2 class="h2 flex-1">Payment schedule</h2>
                        @if($isAdmin || auth()->user()->hasRole('cashier'))<button @click="edit=!edit" class="btn btn-sm" x-text="edit ? 'Cancel' : 'Edit schedule'"></button>@endif</div>
                    @php $left = $paid; @endphp
                    <ul x-show="!edit" class="divide-y divide-slate-100 dark:divide-white/5">
                        @foreach($a->installments as $i)
                            @php $cover = min($left, $i->amount); $left -= $cover; $state = $cover >= $i->amount - 0.009 ? 'paid' : ($i->due_on->isPast() && ! $i->due_on->isToday() ? 'overdue' : ($cover > 0 ? 'partial' : 'upcoming')); @endphp
                            <li class="flex items-center gap-3 py-3 text-sm">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full {{ ['paid' => 'bg-emerald-100 text-emerald-700', 'overdue' => 'bg-rose-100 text-rose-700', 'partial' => 'bg-amber-100 text-amber-700', 'upcoming' => 'bg-slate-100 text-slate-500'][$state] }}">
                                    @if($state === 'paid')<x-icon name="check" class="h-4 w-4" />@elseif($state === 'overdue')<x-icon name="alert" class="h-4 w-4" />@else<x-icon name="clock" class="h-4 w-4" />@endif
                                </span>
                                <span class="flex-1"><span class="font-medium">{{ $i->label }}</span><span class="block text-xs text-slate-500">Due {{ $i->due_on->format('M j, Y') }} · {{ ucfirst($state) }}{{ $state === 'partial' ? ' ('.$peso($cover).' paid)' : '' }}</span></span>
                                <span class="font-semibold">{{ $peso($i->amount) }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <form x-show="edit" x-cloak method="post" action="{{ route('finance.schedule', $e) }}"
                          x-data="{ rows: @js(old('rows', $a->installments->map(fn ($i) => ['label' => $i->label, 'due_on' => $i->due_on->toDateString(), 'amount' => (float) $i->amount])->values())), total: {{ $a->net_amount }} }">
                        @csrf
                        <template x-for="(r, i) in rows" :key="i">
                            <div class="mb-2 grid grid-cols-[1fr_9rem_8rem_auto] items-center gap-2">
                                <input :name="`rows[${i}][label]`" x-model="r.label" class="input py-2">
                                <input type="date" :name="`rows[${i}][due_on]`" x-model="r.due_on" class="input py-2">
                                <input type="number" step="0.01" :name="`rows[${i}][amount]`" x-model.number="r.amount" class="input py-2 text-right">
                                <button type="button" @click="rows.splice(i,1)" class="rounded-lg p-2 text-slate-400 hover:text-rose-600" title="Remove"><x-icon name="x" class="h-4 w-4" /></button>
                            </div>
                        </template>
                        <div class="mt-3 flex flex-wrap items-center gap-3 text-sm">
                            <button type="button" @click="rows.push({ label: 'Payment ' + (rows.length + 1), due_on: rows.length ? rows[rows.length-1].due_on : '{{ now()->toDateString() }}', amount: 0 })" class="btn btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add payment</button>
                            <span class="flex-1 text-right" :class="Math.abs(rows.reduce((t, r) => t + (+r.amount || 0), 0) - total) < 0.01 ? 'text-emerald-700' : 'text-rose-600'">
                                Total <b x-text="'₱' + rows.reduce((t, r) => t + (+r.amount || 0), 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })"></b> of {{ $peso($a->net_amount) }}
                            </span>
                            <button class="btn btn-primary btn-sm">Save schedule</button>
                        </div>
                    </form>
                </div>

                {{-- Fees --}}
                <div class="card" x-data="{ change: false }">
                    <div class="mb-4 flex items-center gap-3"><h2 class="h2 flex-1">Fees</h2>
                        <button @click="change=!change" class="btn btn-sm" x-text="change ? 'Cancel' : 'Change plan or discounts'"></button>
                        @if($isAdmin || auth()->user()->hasRole('cashier'))<a href="{{ route('crud.index', ['fee-items', 'grade_level_id' => $e->grade_level_id]) }}" class="btn btn-sm">Edit {{ $e->gradeLevel->name }} fees</a>@endif</div>
                    <div x-show="change" x-cloak class="mb-5 rounded-xl bg-slate-50 p-4 dark:bg-white/5">@include('finance._assess-form')</div>
                    <dl class="divide-y divide-slate-100 text-sm dark:divide-white/5">
                        @foreach($fees as $f)<div class="flex justify-between py-2.5"><dt>{{ $f->name }} <span class="text-slate-400">{{ $f->category }}</span></dt><dd>{{ $peso($f->amount) }}</dd></div>@endforeach
                        @foreach($a->discounts as $d)<div class="flex justify-between py-2.5 text-emerald-700"><dt>{{ $d->discount->name }}</dt><dd>−{{ $peso($d->amount) }}</dd></div>@endforeach
                        @if(round($a->net_amount - ($a->gross_amount - $a->discount_amount), 2) > 0)<div class="flex justify-between py-2.5"><dt>Installment surcharge</dt><dd>{{ $peso($a->net_amount - ($a->gross_amount - $a->discount_amount)) }}</dd></div>@endif
                        <div class="flex justify-between py-2.5 font-semibold"><dt>Total due</dt><dd>{{ $peso($a->net_amount) }}</dd></div>
                    </dl>
                </div>

                {{-- Payments --}}
                <div class="card p-0">
                    <h2 class="h2 px-6 pt-5 pb-3">Payments received</h2>
                    <table class="table">
                        <thead><tr><th>Date</th><th>Receipt</th><th>Method</th><th class="text-right">Amount</th><th></th></tr></thead>
                        <tbody>
                        @forelse($a->payments->sortByDesc('paid_on') as $p)
                            <tr class="{{ $p->is_void ? 'text-slate-400' : '' }}">
                                <td>{{ $p->paid_on->format('M j, Y') }}</td>
                                <td><a class="link" target="_blank" href="{{ route('finance.receipt', $p) }}">{{ $p->or_number }}</a>@if($p->is_void)<span class="badge ml-2 bg-rose-100 text-rose-700">Void</span>@endif</td>
                                <td>{{ \App\Models\Payment::METHODS[$p->method] }} <span class="text-slate-400">{{ $p->reference }}</span></td>
                                <td class="text-right font-semibold {{ $p->is_void ? 'line-through' : '' }}">{{ $peso($p->amount) }}</td>
                                <td class="text-right">@if(! $p->is_void && $isAdmin)
                                    <form x-data method="post" action="{{ route('finance.void', $p) }}" x-on:submit="const r=prompt('Why is OR {{ $p->or_number }} being voided?'); if(!r){ $event.preventDefault(); return } $el.void_reason.value=r;">@csrf<input type="hidden" name="void_reason"><button class="text-xs text-slate-400 hover:text-rose-600">Void</button></form>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-8 text-center text-slate-500">No payments yet.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Receive payment --}}
            <div>
                <div class="card lg:sticky lg:top-20">
                    <h2 class="h2 mb-4">Receive payment</h2>
                    @if($a->balance() <= 0)
                        <div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"><x-icon name="check-circle" class="mb-1 h-6 w-6" /> Fully paid.</div>
                    @else
                        @php $opts = \App\Support\PayOptions::for($a); @endphp
                        <form method="post" action="{{ route('finance.pay', $e) }}" class="space-y-4"
                              x-data="{ method: '{{ old('method', 'cash') }}', pick: @js($opts[0]['key'] ?? 'other'), other: '{{ old('amount') }}', opts: @js($opts),
                                        get chosen() { return this.opts.find((o) => o.key === this.pick) }, get amount() { return this.pick === 'other' ? this.other : (this.chosen?.amount ?? '') },
                                        get purpose() { return this.pick === 'other' ? 'Partial payment' : this.chosen?.label } }">
                            @csrf
                            <input type="hidden" name="amount" :value="amount"><input type="hidden" name="purpose" :value="purpose"><input type="hidden" name="pay_key" :value="pick">
                            <div>
                                <div class="label">Paying for</div>
                                <div class="space-y-1.5">
                                    <template x-for="o in opts" :key="o.key">
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1" :class="pick === o.key ? 'bg-brand-50 ring-brand-500 dark:bg-brand-500/10' : 'ring-slate-200 dark:ring-slate-700'">
                                            <input type="radio" class="sr-only" :value="o.key" x-model="pick">
                                            <span class="flex-1"><span class="block font-medium" x-text="o.label"></span><span class="text-xs text-slate-500" x-text="o.hint"></span></span>
                                            <span class="font-semibold tabular-nums" x-text="Number(o.amount).toLocaleString('en-PH', { minimumFractionDigits: 2 })"></span>
                                        </label>
                                    </template>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1" :class="pick === 'other' ? 'bg-brand-50 ring-brand-500 dark:bg-brand-500/10' : 'ring-slate-200 dark:ring-slate-700'">
                                        <input type="radio" class="sr-only" value="other" x-model="pick">
                                        <span class="flex-1 font-medium">Other amount</span>
                                        <input type="number" step="0.01" min="1" max="{{ $a->balance() }}" x-model="other" x-on:focus="pick = 'other'" class="input h-9 w-28 text-right" placeholder="0.00">
                                    </label>
                                </div>
                                @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <div class="label">Method</div>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(\App\Models\Payment::METHODS as $k => $v)
                                        <label class="cursor-pointer rounded-xl px-3 py-2 text-center text-sm ring-1" :class="method==='{{ $k }}' ? 'bg-brand-50 font-semibold text-brand-700 ring-brand-500' : 'ring-slate-200 hover:bg-slate-50'">
                                            <input type="radio" name="method" value="{{ $k }}" x-model="method" class="sr-only">{{ \Illuminate\Support\Str::before($v, ' /') }}</label>
                                    @endforeach
                                </div>
                            </div>
                            <div x-show="method !== 'cash'" x-cloak><x-field name="reference" label="Reference / check no." /></div>
                            <x-field name="paid_on" label="Date" type="date" :value="now()->toDateString()" required />
                            <button class="btn btn-primary w-full">Save payment & print receipt</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
