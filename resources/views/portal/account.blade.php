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
            @php
                $ens = $enrollment;
                $feeItems = $a->fees()->orderBy('name')->get();
                $orderCharges = $a->charges()->with('order.lines')->orderBy('created_at')->get();
                $tPaid = $a->tuitionPaid(); $tBal = $a->tuitionBalance();
            @endphp
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="border-b border-slate-100 px-5 py-3 dark:border-slate-800"><h2 class="font-semibold">What you owe</h2></div>
                <div class="divide-y divide-slate-100 dark:divide-slate-800">
                    {{-- tuition --}}
                    <details class="group" open>
                        <summary class="flex cursor-pointer list-none items-center gap-3 px-5 py-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300"><x-icon name="cap" class="h-5 w-5" /></span>
                            <span class="flex-1"><span class="block font-medium">Tuition &amp; school fees</span><span class="text-xs text-slate-500">{{ $a->planName() }} · paid {{ \App\Services\Billing::peso($tPaid) }} of {{ \App\Services\Billing::peso($a->net_amount) }}</span></span>
                            <span class="text-right"><span class="block font-semibold tabular-nums {{ $tBal > 0 ? '' : 'text-emerald-600' }}">{{ $tBal > 0 ? \App\Services\Billing::peso($tBal) : 'Paid' }}</span><span class="text-xs text-slate-400">{{ $tBal > 0 ? 'left' : '' }}</span></span>
                        </summary>
                        <dl class="mx-5 mb-4 rounded-xl bg-slate-50 px-4 py-2 text-sm dark:bg-white/5">
                            @foreach($feeItems as $f)<div class="flex justify-between py-1"><dt class="text-slate-600 dark:text-slate-300">{{ $f->name }}</dt><dd class="tabular-nums">{{ number_format($f->amount, 2) }}</dd></div>@endforeach
                            @foreach($a->discounts as $d)<div class="flex justify-between py-1 text-emerald-700"><dt>{{ $d->discount->name }}</dt><dd class="tabular-nums">−{{ number_format($d->amount, 2) }}</dd></div>@endforeach
                            @if(round($a->net_amount - ($a->gross_amount - $a->discount_amount), 2) > 0)<div class="flex justify-between py-1"><dt>Installment surcharge</dt><dd class="tabular-nums">{{ number_format($a->net_amount - ($a->gross_amount - $a->discount_amount), 2) }}</dd></div>@endif
                            <div class="flex justify-between border-t border-slate-200 py-1.5 font-semibold dark:border-slate-700"><dt>Total</dt><dd class="tabular-nums">{{ number_format($a->net_amount, 2) }}</dd></div>
                        </dl>
                    </details>
                    {{-- orders --}}
                    @foreach($orderCharges as $c)
                        @php $rem = $c->remaining(); @endphp
                        <div class="flex items-center gap-3 px-5 py-4">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300"><x-icon name="tag" class="h-5 w-5" /></span>
                            <span class="min-w-0 flex-1"><span class="block font-medium">{{ $c->description }}</span><span class="block truncate text-xs text-slate-500">{{ $c->order?->summary() }}{{ $c->order ? ' · '.\App\Models\ShopOrder::STATUSES[$c->order->status] : '' }}</span></span>
                            <span class="text-right"><span class="block font-semibold tabular-nums {{ $rem > 0 ? '' : 'text-emerald-600' }}">{{ $rem > 0 ? \App\Services\Billing::peso($rem) : 'Paid' }}</span><span class="text-xs text-slate-400">{{ $rem > 0 && $rem < $c->amount ? 'of '.number_format($c->amount, 2) : '' }}</span></span>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between bg-slate-50 px-5 py-4 dark:bg-white/[.03]"><span class="font-semibold">Total balance</span><span class="text-xl font-bold tabular-nums">{{ \App\Services\Billing::peso($a->balance()) }}</span></div>
                </div>
            </div>

            @php
                $online = \App\Support\School::allows('parent_accounts');
                $channels = $online ? \App\Models\PaymentChannel::where('is_active', true)->orderBy('name')->get() : collect();
                $proofs = \App\Models\PaymentProof::with('channel')->where('student_account_id', $a->id)->latest()->take(5)->get();
                $next = $a->installments->sortBy('due_on')->first(fn ($i) => $i->due_on->isPast() || true);
                $suggest = $a->overdueAmount() > 0 ? $a->overdueAmount() : min($a->balance(), (float) ($a->installments->first()?->amount ?? $a->balance()));
            @endphp
            @if($a->overdueAmount() > 0)<p class="mt-3 rounded-lg bg-red-50 p-3 text-sm font-semibold text-red-800">Overdue: {{ \App\Services\Billing::peso($a->overdueAmount()) }}. {{ $channels->isNotEmpty() ? 'Pay online below or at the cashier.' : 'Please settle at the cashier.' }}</p>@endif

            @if($a->balance() > 0 && $channels->isNotEmpty())
                @php $opts = \App\Support\PayOptions::for($a); @endphp
                <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900"
                     x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, step: 1, sel: [@js(collect($opts)->firstWhere(fn ($o) => $o['key'] !== 'full')['key'] ?? 'full')], other: '', ch: {{ $channels->first()->id }}, preview: null,
                               opts: @js($opts), max: {{ $a->balance() }},
                               // Full balance and another amount stand alone; everything else can be combined.
                               solo(k) { return ['full', 'other'].includes(k) },
                               toggle(k) { if (this.solo(k)) { this.sel = [k]; return } this.sel = this.sel.filter((x) => !this.solo(x)); this.sel.includes(k) ? this.sel = this.sel.filter((x) => x !== k) : this.sel.push(k) },
                               on(k) { return this.sel.includes(k) },
                               get pick() { return this.sel.join(',') },
                               get chosen() { return this.opts.filter((o) => this.sel.includes(o.key)) },
                               get amount() { return this.on('other') ? Number(this.other || 0) : Math.round(this.chosen.reduce((t, o) => t + o.amount, 0) * 100) / 100 },
                               get purpose() { return this.on('other') ? 'Partial payment' : this.chosen.map((o) => o.label).join(' + ') },
                               peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) },
                               charges: @js($a->charges()->orderBy('created_at')->get()->map(fn ($c) => ['key' => 'order:'.$c->id, 'label' => $c->description, 'left' => $c->remaining()])->filter(fn ($c) => $c['left'] > 0)->values()), tuition: {{ $a->tuitionBalance() }},
                               // Same rule the school uses when it records the payment.
                               split() {
                                   let left = this.amount, out = [];
                                   const take = (label, cap) => { const t = Math.min(left, cap); if (t > 0) { out.push({ label, amount: t }); left = Math.round((left - t) * 100) / 100 } };
                                   const picked = this.charges.filter((c) => this.on(c.key));
                                   const multi = !this.solo(this.sel[0] || '');
                                   if (multi) { picked.forEach((c) => take(c.label, c.left)); take('Tuition & school fees', this.tuition); return out }
                                   if (this.on('overdue')) take('Tuition & school fees', this.tuition);
                                   this.charges.forEach((c) => take(c.label, c.left));
                                   take('Tuition & school fees', this.tuition);
                                   return out;
                               } }">
                    <div class="flex flex-wrap items-center gap-3 p-5">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-icon name="wallet" class="h-5 w-5" /></span>
                        <div class="mr-auto"><h2 class="text-lg font-semibold">Pay online</h2><p class="text-sm text-slate-500">Pay by GCash or bank, then send the receipt. The cashier confirms it and the official receipt appears here.</p></div>
                        <button type="button" class="btn btn-primary" x-show="!open" x-on:click="open = true">Make a payment</button>
                    </div>
                    <form x-show="open" x-cloak method="post" action="{{ route('portal.pay', $child) }}" enctype="multipart/form-data" class="border-t border-slate-100 dark:border-slate-800">@csrf
                        <input type="hidden" name="amount" :value="amount.toFixed(2)"><input type="hidden" name="purpose" :value="purpose"><input type="hidden" name="pay_key" :value="pick">
                        {{-- steps header --}}
                        <ol class="flex border-b border-slate-100 text-sm dark:border-slate-800">
                            @foreach(['What for', 'Pay', 'Send receipt'] as $n => $label)
                                <li class="flex flex-1 items-center gap-2 px-5 py-3" :class="step === {{ $n + 1 }} ? 'font-semibold text-slate-900 dark:text-white' : 'text-slate-400'">
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full text-xs" :class="step > {{ $n + 1 }} ? 'bg-brand-600 text-white' : (step === {{ $n + 1 }} ? 'border-2 border-brand-600 text-brand-600' : 'border border-slate-300')">{{ $n + 1 }}</span>{{ $label }}
                                </li>
                            @endforeach
                        </ol>
                        <div class="grid gap-0 md:grid-cols-[minmax(0,1fr)_260px]">
                            <div class="p-5">
                                {{-- 1: what for --}}
                                <div x-show="step === 1" class="space-y-2">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-xs text-slate-600 dark:text-slate-400">Tick what you want to pay.</p>
                                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm font-medium text-brand-700 hover:bg-brand-50 dark:text-brand-300 dark:hover:bg-brand-500/10">
                                            <input type="checkbox" class="h-4 w-4 accent-brand-600" :checked="on('full')" x-on:change="sel = on('full') ? [] : ['full']">
                                            Select all <span class="font-normal text-slate-600 dark:text-slate-400" x-text="'(full balance ' + peso(max) + ')'"></span>
                                        </label>
                                    </div>
                                    <template x-for="o in opts.filter((x) => x.key !== 'full')" :key="o.key">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3.5" :class="on(o.key) || on('full') ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                                            <input :type="solo(o.key) ? 'radio' : 'checkbox'" class="h-4 w-4 shrink-0 accent-brand-600" :checked="on(o.key) || on('full')" x-on:change="toggle(o.key)">
                                            <span class="flex-1"><span class="block font-medium" x-text="o.label"></span><span class="text-xs text-slate-500" x-text="o.hint"></span></span>
                                            <span class="font-semibold tabular-nums" x-text="peso(o.amount)"></span>
                                        </label>
                                    </template>
                                    <label class="block cursor-pointer rounded-xl border-2 p-3.5" :class="on('other') ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'" x-on:click="if (!on('other')) { toggle('other'); $nextTick(() => $refs.oth.focus()) }">
                                        <span class="block font-medium">Another amount</span>
                                        <span class="text-xs text-slate-600 dark:text-slate-400">Pay any part of the balance (up to <span x-text="peso(max)"></span>)</span>
                                        <span x-show="on('other')" x-cloak class="mt-3 flex items-center rounded-xl border border-slate-300 bg-white px-4 focus-within:border-brand-500 focus-within:ring-2 focus-within:ring-brand-500/30 dark:border-slate-600 dark:bg-slate-900">
                                            <span class="text-2xl font-semibold text-slate-500">₱</span>
                                            <input x-ref="oth" type="text" inputmode="decimal" x-model="other" class="h-14 w-full border-0 bg-transparent px-2 text-2xl font-bold tabular-nums focus:ring-0" placeholder="0.00" aria-label="Amount to pay">
                                        </span>
                                        <span x-show="on('other') && other !== '' && (Number(other) <= 0 || Number(other) > max)" class="mt-1 block text-xs font-medium text-rose-700" x-text="Number(other) > max ? 'That is more than the balance.' : 'Enter an amount above ₱0.'"></span>
                                    </label>
                                </div>
                                {{-- 2: pay --}}
                                <div x-show="step === 2" x-cloak>
                                    <div class="mb-3 flex flex-wrap gap-2">
                                        @foreach($channels as $c)
                                            <label class="cursor-pointer rounded-full border-2 px-4 py-1.5 text-sm font-medium" :class="ch === {{ $c->id }} ? 'border-brand-600 bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-200' : 'border-slate-200 dark:border-slate-700'">
                                                <input type="radio" name="payment_channel_id" value="{{ $c->id }}" class="sr-only" x-model.number="ch" @checked($loop->first)>{{ $c->name }}</label>
                                        @endforeach
                                    </div>
                                    @foreach($channels as $c)
                                        <div x-show="ch === {{ $c->id }}" class="flex flex-wrap gap-5 rounded-xl bg-slate-50 p-4 dark:bg-white/5">
                                            @if($c->qr_path)<img src="{{ route('channels.qr', $c) }}" alt="QR code for {{ $c->name }}" class="h-48 w-48 rounded-xl border border-slate-200 bg-white object-contain p-2">@endif
                                            <div class="min-w-0 flex-1 space-y-2 text-sm">
                                                <p class="text-xs uppercase tracking-wider text-slate-400">Send exactly</p>
                                                <p class="text-3xl font-bold tabular-nums" x-text="peso(amount)"></p>
                                                @if($c->account_name)<p><span class="text-slate-500">Account name</span><br><b>{{ $c->account_name }}</b></p>@endif
                                                @if($c->account_number)<p><span class="text-slate-500">Number</span><br><b class="font-mono text-base">{{ $c->account_number }}</b></p>@endif
                                                @if($c->instructions)<p class="text-slate-600 dark:text-slate-300">{{ $c->instructions }}</p>@endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                {{-- 3: receipt --}}
                                <div x-show="step === 3" x-cloak class="space-y-4">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <label class="block"><span class="label">Reference number</span><input name="reference" value="{{ old('reference') }}" class="input" placeholder="From the GCash / bank receipt" :required="step === 3"></label>
                                        <label class="block"><span class="label">Date paid</span><input name="paid_on" type="date" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="input"></label>
                                    </div>
                                    <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-slate-300 p-6 text-center hover:border-brand-400 dark:border-slate-700">
                                        <img x-show="preview" :src="preview" alt="" class="max-h-48 rounded-lg">
                                        <x-icon name="download" class="h-6 w-6 text-slate-400" x-show="!preview" />
                                        <span class="text-sm font-medium" x-text="preview ? 'Change screenshot' : 'Upload a screenshot of your receipt'"></span>
                                        <input type="file" name="screenshot" accept="image/*" class="sr-only" x-on:change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : null">
                                    </label>
                                    @if($errors->any())<p class="field-error">{{ $errors->first() }}</p>@endif
                                </div>
                            </div>
                            {{-- summary --}}
                            <aside class="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 p-5 md:border-t-0 md:border-l dark:border-slate-800 dark:bg-white/[.02]">
                                <p class="text-xs uppercase tracking-wider text-slate-400">You're paying</p>
                                <p class="text-3xl font-bold tabular-nums" x-text="peso(amount)"></p>
                                <p class="text-sm text-slate-600 dark:text-slate-300" x-text="purpose"></p>
                                <div class="space-y-1 rounded-lg bg-white p-3 text-xs dark:bg-slate-900" x-show="amount > 0">
                                    <p class="mb-1 font-semibold text-slate-500">Where it goes</p>
                                    <template x-for="l in split()" :key="l.label"><p class="flex justify-between gap-2"><span class="truncate" x-text="l.label"></span><span class="tabular-nums" x-text="peso(l.amount)"></span></p></template>
                                </div>
                                <p class="text-xs text-slate-500">Balance after: <span x-text="peso(Math.max(0, max - amount))"></span></p>
                                <p x-show="amount > max" class="text-xs font-semibold text-rose-600">That's more than the balance.</p>
                                <div class="mt-auto flex gap-2 pt-4">
                                    <button type="button" class="btn flex-1" x-show="step > 1" x-on:click="step--">Back</button>
                                    <button type="button" class="btn btn-primary flex-1" x-show="step < 3" :disabled="amount <= 0 || amount > max" x-on:click="step++">Next</button>
                                    <button class="btn btn-primary flex-1" x-show="step === 3" x-cloak :disabled="!preview">Send</button>
                                </div>
                            </aside>
                        </div>
                    </form>
                </div>
            @endif
            @if($proofs->isNotEmpty())
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-2 font-semibold">Online payments you sent</h2>
                    @foreach($proofs as $pp)
                        <p class="flex flex-wrap justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0 dark:border-slate-800">
                            <span>{{ $pp->paid_on->format('M d, Y') }} · {{ $pp->purpose ?: 'Payment' }} · {{ $pp->channel?->name }} · ref {{ $pp->reference }}</span>
                            <span class="font-medium">{{ number_format($pp->amount, 2) }}
                                <span class="ml-2 rounded-full px-2 py-0.5 text-xs {{ ['pending' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-emerald-100 text-emerald-700', 'rejected' => 'bg-rose-100 text-rose-700'][$pp->status] }}">{{ ['pending' => 'Being checked', 'approved' => 'Confirmed', 'rejected' => 'Not accepted'][$pp->status] }}</span></span>
                            @if($pp->note)<span class="w-full text-xs text-rose-600">{{ $pp->note }}</span>@endif
                        </p>
                    @endforeach
                </div>
            @endif
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                <div class="card"><h2 class="h2 mb-2">Installments</h2>@foreach($a->installments as $i)<p class="flex justify-between text-sm"><span>{{ $i->label }} · due {{ $i->due_on->format('M d, Y') }}</span><span>{{ number_format($i->amount, 2) }}</span></p>@endforeach</div>
                <div class="card"><h2 class="h2 mb-2">Payments</h2>@forelse($a->payments->where('is_void', false) as $p)<p class="flex justify-between text-sm"><span>{{ $p->paid_on->format('M d, Y') }} · <a class="link" target="_blank" href="{{ route('finance.receipt', $p) }}">{{ $p->or_number }}</a></span><span>{{ number_format($p->amount, 2) }}</span></p>@empty<p class="muted">None yet.</p>@endforelse</div>
            </div>
        @endif
    @endif
</x-layouts.app>
