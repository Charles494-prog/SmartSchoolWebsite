@php $peso = fn ($v) => \App\Services\Billing::peso($v); @endphp
<x-layouts.app title="Bookstore orders awaiting payment">
    <x-page-header title="Bookstore orders awaiting payment" subtitle="Receive the payment with the usual official receipt. Walk-in students are listed first. This list refreshes by itself." />
    <div x-data="{ n: {{ $orders->count() }} }" x-init="setInterval(async () => { if (document.hidden) return; try { const r = await fetch(@js(route('finance.bookstore')), { headers: { Accept: 'application/json' } }); const j = await r.json(); if (j.count !== n) location.reload() } catch (e) {} }, 5000)">
        @if($orders->isEmpty())
            <div class="card"><x-empty icon="check-circle" title="Nothing to collect" text="Bookstore orders that still need payment show up here." /></div>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:divide-slate-800 dark:border-slate-800 dark:bg-slate-900">
                @foreach($orders as $o)
                    @php $e = $o->student->currentEnrollment(); @endphp
                    <li class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <div class="min-w-0 flex-1 basis-60"><p class="font-semibold break-words">#{{ $o->id }} · {{ $o->student->full_name }} @if($o->walk_in)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-xs font-semibold text-amber-900">Walk-in – at the counter</span>@endif</p>
                            <p class="text-sm text-slate-600 dark:text-slate-400 break-words">{{ $o->summary() }} · {{ $o->created_at->format('M j, Y') }} {{ ampm($o->created_at->format('H:i')) }}</p></div>
                        <span class="text-lg font-bold tabular-nums">{{ $peso($o->charge->remaining()) }}</span>
                        @if($e)<a href="{{ route('finance.show', [$e, 'pay' => 'order:'.$o->charge->id]) }}" class="btn btn-primary min-h-11">Receive payment</a>@endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts.app>
