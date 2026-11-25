<x-layouts.app title="Online payments">
    <div class="mb-5"><h1 class="h1">Online payments</h1><p class="muted">Payments families made through GCash or the bank. Check the screenshot against your account, then approve to issue the official receipt.</p></div>
    <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
        @foreach(['pending' => 'To check', 'approved' => 'Approved', 'rejected' => 'Not accepted'] as $k => $label)
            <a href="{{ route('finance.proofs', ['status' => $k]) }}" class="rounded-lg px-3 py-1.5 font-medium {{ $status === $k ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500' }}">{{ $label }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
    </div>
    <div class="space-y-3">
        @forelse($proofs as $p)
            @php $st = $p->account->enrollment->student; @endphp
            <div class="flex flex-wrap gap-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                <a href="{{ route('proofs.screenshot', $p) }}" target="_blank" class="block"><img src="{{ route('proofs.screenshot', $p) }}" alt="Payment screenshot" class="h-40 w-32 rounded-lg border border-slate-200 object-cover"></a>
                <div class="min-w-0 flex-1 basis-64">
                    <p class="text-2xl font-bold tabular-nums">{{ \App\Services\Billing::peso($p->amount) }} @if($p->purpose)<span class="text-sm font-normal text-slate-500">· {{ $p->purpose }}</span>@endif</p>
                    <p class="font-medium">{{ $st->full_name }} <span class="text-sm text-slate-500">{{ $p->account->enrollment->section?->label() }}</span></p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $p->channel?->name ?? 'Payment method removed' }} · ref <span class="font-mono">{{ $p->reference }}</span> · paid {{ $p->paid_on->format('M d, Y') }}</p>
                    <p class="text-sm text-slate-500">Sent by {{ $p->submitter?->name }} · {{ $p->created_at->diffForHumans() }} · balance now {{ \App\Services\Billing::peso($p->account->balance()) }}</p>
                    @if($p->note)<p class="mt-1 text-sm text-rose-600">{{ $p->note }}</p>@endif
                </div>
                @if($p->status === 'pending')
                    <form method="post" action="{{ route('finance.proofs.review', $p) }}" class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:flex-col sm:items-stretch" x-data="{ no: false }">@csrf
                        <button name="action" value="approve" class="btn btn-primary">Approve &amp; issue receipt</button>
                        <button type="button" class="btn" x-show="!no" x-on:click="no = true">Not accepted…</button>
                        <div x-show="no" x-cloak class="space-y-2"><input name="note" class="input" placeholder="Why? e.g. amount not received"><button name="action" value="reject" class="btn w-full text-rose-600">Send</button></div>
                    </form>
                @elseif($p->payment_id)
                    <a href="{{ route('finance.receipt', $p->payment_id) }}" class="btn h-fit">Receipt</a>
                @endif
            </div>
        @empty
            <div class="card py-10 text-center text-slate-500">Nothing here.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $proofs->links() }}</div>
</x-layouts.app>
