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
                     x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, step: 1, pick: @js($opts[0]['key'] ?? 'other'), other: '', ch: {{ $channels->first()->id }}, preview: null,
                               opts: @js($opts), max: {{ $a->balance() }},
                               get chosen() { return this.opts.find((o) => o.key === this.pick) },
                               get amount() { return this.pick === 'other' ? Number(this.other || 0) : (this.chosen?.amount || 0) },
                               get purpose() { return this.pick === 'other' ? 'Partial payment' : this.chosen?.label },
                               peso(v) { return '₱' + Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) } }">
                    <div class="flex flex-wrap items-center gap-3 p-5">
                        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600 dark:bg-brand-500/10 dark:text-brand-300"><x-icon name="wallet" class="h-5 w-5" /></span>
                        <div class="mr-auto"><h2 class="text-lg font-semibold">Pay online</h2><p class="text-sm text-slate-500">Pay by GCash or bank, then send the receipt. The cashier confirms it and the official receipt appears here.</p></div>
                        <button type="button" class="btn btn-primary" x-show="!open" x-on:click="open = true">Make a payment</button>
                    </div>
                    <form x-show="open" x-cloak method="post" action="{{ route('portal.pay', $child) }}" enctype="multipart/form-data" class="border-t border-slate-100 dark:border-slate-800">@csrf
                        <input type="hidden" name="amount" :value="amount.toFixed(2)"><input type="hidden" name="purpose" :value="purpose">
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
                                    <template x-for="o in opts" :key="o.key">
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border-2 p-3.5" :class="pick === o.key ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                                            <input type="radio" class="sr-only" :value="o.key" x-model="pick">
                                            <span class="flex-1"><span class="block font-medium" x-text="o.label"></span><span class="text-xs text-slate-500" x-text="o.hint"></span></span>
                                            <span class="font-semibold tabular-nums" x-text="peso(o.amount)"></span>
                                        </label>
                                    </template>
                                    <label class="flex cursor-pointer flex-wrap items-center gap-3 rounded-xl border-2 p-3.5" :class="pick === 'other' ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                                        <input type="radio" class="sr-only" value="other" x-model="pick">
                                        <span class="flex-1"><span class="block font-medium">Another amount</span><span class="text-xs text-slate-500">Pay part of the balance</span></span>
                                        <span class="flex items-center gap-1">₱<input type="number" step="0.01" min="1" :max="max" x-model="other" x-on:focus="pick = 'other'" class="input h-10 w-32 text-right" placeholder="0.00"></span>
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
