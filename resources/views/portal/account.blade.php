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
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ open: {{ $errors->any() ? 'true' : 'false' }}, ch: {{ $channels->first()->id }} }">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="mr-auto"><h2 class="text-lg font-semibold">Pay online</h2><p class="text-sm text-slate-500">Pay through GCash or the bank, then send us a screenshot. The cashier checks it and sends the official receipt here.</p></div>
                        <button type="button" class="btn btn-primary" x-show="!open" x-on:click="open = true">Make a payment</button>
                    </div>
                    <form x-show="open" x-cloak method="post" action="{{ route('portal.pay', $child) }}" enctype="multipart/form-data" class="mt-5 grid gap-5 md:grid-cols-2">@csrf
                        <div>
                            <p class="label">1. Choose where to pay</p>
                            <div class="space-y-2">
                                @foreach($channels as $c)
                                    <label class="block cursor-pointer rounded-xl border-2 p-3" :class="ch === {{ $c->id }} ? 'border-brand-600 bg-brand-50 dark:bg-brand-500/10' : 'border-slate-200 dark:border-slate-700'">
                                        <input type="radio" name="payment_channel_id" value="{{ $c->id }}" class="sr-only" x-model.number="ch" @checked($loop->first)>
                                        <span class="font-semibold">{{ $c->name }}</span> <span class="text-sm text-slate-500">{{ $c->account_name }}{{ $c->account_number ? ' · '.$c->account_number : '' }}</span>
                                        <div x-show="ch === {{ $c->id }}" class="mt-3 flex gap-3">
                                            @if($c->qr_path)<img src="{{ route('channels.qr', $c) }}" alt="QR code for {{ $c->name }}" class="h-36 w-36 rounded-lg border border-slate-200 bg-white object-contain p-1">@endif
                                            @if($c->instructions)<p class="text-sm text-slate-600 dark:text-slate-300">{{ $c->instructions }}</p>@endif
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="label">2. Tell us what you paid</p>
                            <label class="block"><span class="text-sm">Amount</span><input name="amount" type="number" step="0.01" min="1" max="{{ $a->balance() }}" value="{{ old('amount', number_format($suggest, 2, '.', '')) }}" class="input" required>
                                <span class="text-xs text-slate-500">Balance {{ \App\Services\Billing::peso($a->balance()) }} – you can pay part of it.</span></label>
                            <label class="block"><span class="text-sm">Reference number</span><input name="reference" value="{{ old('reference') }}" class="input" placeholder="From your GCash / bank receipt" required></label>
                            <label class="block"><span class="text-sm">Date paid</span><input name="paid_on" type="date" value="{{ old('paid_on', today()->toDateString()) }}" max="{{ today()->toDateString() }}" class="input" required></label>
                            <label class="block"><span class="text-sm">Screenshot of the receipt</span><input name="screenshot" type="file" accept="image/*" class="block text-sm" required></label>
                            @if($errors->any())<p class="field-error">{{ $errors->first() }}</p>@endif
                            <button class="btn btn-primary w-full">Send to the cashier</button>
                        </div>
                    </form>
                </div>
            @endif
            @if($proofs->isNotEmpty())
                <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                    <h2 class="mb-2 font-semibold">Online payments you sent</h2>
                    @foreach($proofs as $pp)
                        <p class="flex flex-wrap justify-between gap-2 border-t border-slate-100 py-2 text-sm first:border-0 dark:border-slate-800">
                            <span>{{ $pp->paid_on->format('M d, Y') }} · {{ $pp->channel?->name }} · ref {{ $pp->reference }}</span>
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
