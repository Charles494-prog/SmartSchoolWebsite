@php
    $peso = fn ($v) => \App\Services\Billing::peso((float) $v);
    $isAdmin = auth()->user()->isAdmin();
@endphp
<x-layouts.app :title="'Account · '.$e->student->full_name" :hideErrorSummary="true">
    <x-page-header :title="$e->student->full_name" :back="route('finance.index')" backLabel="Transactions"
                   :subtitle="$e->gradeLevel->name.($e->section ? ' – '.$e->section->name : '').' · S.Y. '.$e->schoolYear->name.' · School ID '.$e->student->student_no">
        @if($a)<a href="{{ route('finance.soa', $e) }}" target="_blank" class="btn"><x-icon name="file" class="h-4 w-4" /> Statement (PDF)</a>@endif
    </x-page-header>

    <div class="sticky top-16 z-20 -mx-4 mb-4 border-b border-slate-200 bg-slate-50/95 px-4 py-2 backdrop-blur sm:-mx-6 sm:px-6 dark:border-slate-800 dark:bg-slate-950/95">
        <nav class="text-xs text-slate-600 dark:text-slate-400"><a href="{{ route('finance.index') }}" class="hover:underline">Transactions</a> <span aria-hidden="true">›</span> <span class="font-semibold text-slate-900 dark:text-white">{{ $e->student->full_name }}</span> · School ID {{ $e->student->student_no }}</nav>
    </div>
    <div class="pb-24">
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
        @php
            $pct = $a->net_amount > 0 ? min(100, round($paid / $a->net_amount * 100)) : 100; $overdue = $a->overdueAmount();
            $covered = $a->tuitionPaid(); $firstLate = null;
            foreach ($a->installments->sortBy('due_on') as $ins) { if ($covered >= $ins->amount - 0.005) { $covered -= $ins->amount; continue; } if ($ins->due_on->lt(today())) { $firstLate = $ins; } break; }
            $daysLate = $firstLate ? (int) $firstLate->due_on->diffInDays(today()) : 0;
        @endphp
        {{-- Summary --}}
        <div class="card mb-6">
            <div class="grid gap-6 sm:grid-cols-3">
                <div><div class="muted">Total due</div><div class="mt-1 text-2xl font-bold">{{ $peso($a->net_amount) }}</div><div class="text-xs text-slate-600 dark:text-slate-400">{{ $a->planName() }}</div></div>
                <div><div class="muted">Paid</div><div class="mt-1 text-2xl font-bold {{ $paid > 0 ? 'text-emerald-700' : 'text-slate-700 dark:text-slate-200' }}">{{ $peso($paid) }}</div><div class="text-xs text-slate-600 dark:text-slate-400">{{ $pct }}% paid</div></div>
                <div><div class="muted">Balance</div><div class="mt-1 text-2xl font-bold {{ $a->balance() > 0 ? 'text-slate-900' : 'text-emerald-700' }}">{{ $peso($a->balance()) }}</div>
                    @if($overdue > 0)<div class="text-xs font-semibold text-rose-700">{{ $peso($overdue) }} · {{ $daysLate }} {{ str('day')->plural($daysLate) }} overdue</div>@else<div class="text-xs text-slate-600 dark:text-slate-400">Nothing overdue</div>@endif</div>
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
                        @if($isAdmin || auth()->user()->hasRole('cashier'))<a href="{{ route('crud.index', ['fee-items', 'grade_level_id' => $e->grade_level_id]) }}" class="text-xs font-medium text-slate-600 underline-offset-2 hover:underline dark:text-slate-300"
                           x-on:click="if (!confirm(@js('This changes the fees for ALL '.$e->gradeLevel->name.' students assessed from now on. Students already assessed (like this one) keep what they were charged. Continue?'))) $event.preventDefault()">Edit fees for all {{ $e->gradeLevel->name }}</a>@endif</div>
                    <div x-show="change" x-cloak class="mb-5 rounded-xl bg-slate-50 p-4 dark:bg-white/5">@include('finance._assess-form')</div>
                    <dl class="divide-y divide-slate-100 text-sm dark:divide-white/5">
                        @foreach($fees as $f)<div class="flex justify-between py-2.5"><dt>{{ $f->name }}@if($f->category && ! str_contains(strtolower($f->name), strtolower($f->category))) <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-[11px] text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $f->category }}</span>@endif</dt><dd>{{ $peso($f->amount) }}</dd></div>@endforeach
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
                <div class="card lg:sticky lg:top-28 lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                    <h2 class="h2 mb-4">Receive payment</h2>
                    @if($a->balance() <= 0)
                        <div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800"><x-icon name="check-circle" class="mb-1 h-6 w-6" /> Fully paid.</div>
                    @else
                        @php $opts = \App\Support\PayOptions::for($a); $bal = $a->balance(); @endphp
                        @php $first = collect($opts)->firstWhere(fn ($o) => $o['key'] !== 'full')['key'] ?? 'full'; $want = collect($opts)->pluck('key')->contains(request('pay')) ? request('pay') : $first; $oldSel = old('pay_key') ? explode(',', old('pay_key')) : [$want]; @endphp
                        <form method="post" action="{{ route('finance.pay', $e) }}" class="flex flex-col" x-ref="pay"
                              x-data="{ method: '{{ old('method', 'cash') }}', sel: @js($oldSel), other: '{{ old('amount') }}', opts: @js($opts), bal: {{ $bal }}, busy: false, ref: '{{ old('reference') }}',
                                        // Same picker as the family's Pay online: tick several items; Full balance and Other amount stand alone.
                                        solo(k) { return ['full', 'other'].includes(k) },
                                        toggle(k) { if (this.solo(k)) { this.sel = [k]; return } this.sel = this.sel.filter((x) => !this.solo(x)); this.sel.includes(k) ? this.sel = this.sel.filter((x) => x !== k) : this.sel.push(k) },
                                        on(k) { return this.sel.includes(k) },
                                        get pick() { return this.sel.join(',') },
                                        get chosen() { return this.opts.filter((o) => this.sel.includes(o.key)) },
                                        get amount() { return this.on('other') ? Number(this.other || 0) : Math.round(this.chosen.reduce((t, o) => t + o.amount, 0) * 100) / 100 },
                                        get purpose() { return this.on('other') ? 'Partial payment' : this.chosen.map((o) => o.label).join(' + ') },
                                        get otherError() { if (!this.on('other') || this.other === '') return ''; const v = Number(this.other); if (!(v > 0)) return 'Enter an amount above ₱0.'; if (v > this.bal + 0.001) return 'That is more than the balance (' + this.peso(this.bal) + ').'; if (!/^\d+(\.\d{1,2})?$/.test(String(this.other))) return 'Use at most 2 decimal places.'; return '' },
                                        get refNeeded() { return this.method !== 'cash' },
                                        get ok() { return this.amount > 0 && this.amount <= this.bal + 0.001 && !this.otherError && (!this.refNeeded || this.ref.trim() !== '') },
                                        peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                                        submit() { if (!this.ok || this.busy) return; const m = { cash: 'Cash', gcash: 'GCash', bank: 'Bank transfer', check: 'Check' }[this.method] || this.method;
                                            if (!confirm('Record ' + this.peso(this.amount) + ' by ' + m + ' for {{ addslashes($e->student->full_name) }}?')) return; this.busy = true; $refs.pay.submit() } }"
                              x-on:submit.prevent="submit()">
                            @csrf
                            <input type="hidden" name="amount" :value="amount.toFixed(2)"><input type="hidden" name="purpose" :value="purpose"><input type="hidden" name="pay_key" :value="pick">
                            <fieldset class="space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <legend class="label mb-0">Paying for</legend>
                                    <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-2 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                        <input type="checkbox" class="h-4 w-4 accent-brand-600" :checked="on('full')" x-on:change="sel = on('full') ? [] : ['full']">
                                        Select all <span class="font-normal text-slate-600 dark:text-slate-400" x-text="'(' + peso(bal) + ')'"></span>
                                    </label>
                                </div>
                                <p class="text-xs text-slate-600 dark:text-slate-400">Tick one or more items.</p>
                                <template x-for="o in opts.filter((x) => x.key !== 'full')" :key="o.key">
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3 text-sm" :class="on(o.key) || on('full') ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                                        <input :type="solo(o.key) ? 'radio' : 'checkbox'" class="h-4 w-4 shrink-0 accent-brand-600" :checked="on(o.key) || on('full')" x-on:change="toggle(o.key)">
                                        <span class="min-w-0 flex-1"><span class="block font-medium" x-text="o.label"></span><span class="block text-xs text-slate-600 dark:text-slate-400" x-text="o.hint"></span></span>
                                        <span class="font-semibold tabular-nums" x-text="peso(o.amount)"></span>
                                    </label>
                                </template>
                                <label class="block cursor-pointer rounded-xl border-2 p-3 text-sm" :class="on('other') ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'" x-on:click="if (!on('other')) { toggle('other'); $nextTick(() => $refs.oth.focus()) }">
                                    <span class="block font-medium">Another amount</span>
                                    <span class="block text-xs text-slate-600 dark:text-slate-400">Any part of the balance (up to <span x-text="peso(bal)"></span>)</span>
                                    <span x-show="on('other')" x-cloak class="mt-2 flex items-center rounded-xl border border-slate-300 bg-white px-3 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-900">
                                        <span class="text-xl font-semibold text-slate-500">₱</span>
                                        <input x-ref="oth" type="text" inputmode="decimal" x-model="other" class="h-12 w-full border-0 bg-transparent px-2 text-xl font-bold tabular-nums focus:ring-0" placeholder="0.00" aria-label="Other amount" :aria-invalid="!!otherError" aria-describedby="other-err">
                                    </span>
                                    <p id="other-err" x-show="otherError" x-text="otherError" class="mt-1 text-xs font-medium text-rose-700" role="alert"></p>
                                </label>
                                @error('amount')<p class="field-error">{{ $message }}</p>@enderror
                            </fieldset>
                            <fieldset class="mt-4">
                                <legend class="label">Method</legend>
                                <div class="grid grid-cols-2 gap-2">
                                    @foreach(\App\Models\Payment::METHODS as $k => $v)
                                        <label class="flex cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-sm ring-1 focus-within:ring-2 focus-within:ring-brand-500" :class="method==='{{ $k }}' ? 'bg-brand-50 font-semibold text-brand-800 ring-2 ring-brand-500 dark:bg-brand-500/10 dark:text-brand-200' : 'ring-slate-200 hover:bg-slate-50 dark:ring-slate-700'">
                                            <input type="radio" name="method" value="{{ $k }}" x-model="method" class="h-4 w-4 accent-brand-600">{{ \Illuminate\Support\Str::before($v, ' /') }}</label>
                                    @endforeach
                                </div>
                            </fieldset>
                            <label class="mt-4 block" x-show="refNeeded" x-cloak><span class="label">Reference / check no. <span class="text-rose-600">*</span></span>
                                <input name="reference" x-model="ref" class="input" :required="refNeeded" placeholder="From the GCash / bank slip / check">
                                @error('reference')<span class="field-error">{{ $message }}</span>@enderror</label>
                            <p class="mt-4 text-sm text-slate-600 dark:text-slate-400">Date: <b class="text-slate-900 dark:text-white">{{ now()->format('M j, Y') }}</b> <span class="text-xs">(today – set automatically)</span></p>
                            <div class="sticky bottom-0 -mx-6 mt-5 border-t border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
                                <p class="mb-1 truncate text-xs text-slate-600 dark:text-slate-400" x-text="purpose"></p>
                                <div class="mb-2 flex items-baseline justify-between"><span class="text-sm text-slate-600 dark:text-slate-400">Amount to receive</span><span class="text-2xl font-bold tabular-nums" x-text="peso(amount)"></span></div>
                                <button class="btn btn-primary min-h-12 w-full text-base" :disabled="!ok || busy" x-text="busy ? 'Recording…' : 'Record payment'">Record payment</button>
                                <p x-show="refNeeded && !ref.trim()" class="mt-1.5 text-xs text-slate-600">Enter the reference number to continue.</p>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
    </div>
</x-layouts.app>
