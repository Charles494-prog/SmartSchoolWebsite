<x-layouts.app title="Request documents">
    @php $peso = fn ($v) => \App\Services\Billing::peso($v); @endphp
    <div class="mb-5"><h1 class="h1">Request documents</h1><p class="muted">1. Request · 2. Pay the fee on Payments (if any) · 3. The registrar tells you when it's ready.</p></div>
    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="grid gap-3 sm:grid-cols-2">
            @forelse($services as $s)
                <form method="post" action="{{ $child ? route('portal.services.request', $child) : '#' }}" class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ open: false }">@csrf
                    <input type="hidden" name="school_service_id" value="{{ $s->id }}">
                    <div class="flex items-start gap-3"><span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300"><x-icon name="file" class="h-5 w-5" /></span>
                        <div class="flex-1"><p class="font-semibold">{{ $s->name }}</p>@if($s->description)<p class="text-sm text-slate-600 dark:text-slate-400">{{ $s->description }}</p>@endif
                            <p class="mt-1 text-sm"><b>{{ $s->fee > 0 ? $peso($s->fee) : 'Free' }}</b> <span class="text-slate-600 dark:text-slate-400">· ready in about {{ $s->days }} {{ str('day')->plural($s->days) }}</span></p></div></div>
                    <button type="button" class="btn btn-sm mt-4" x-show="!open" x-on:click="open = true">Request</button>
                    <div x-show="open" x-cloak class="mt-4 space-y-2">
                        <div class="flex gap-2"><label class="text-sm">Copies <input type="number" name="copies" value="1" min="1" max="10" class="input h-9 w-20"></label></div>
                        <input name="purpose" class="input" placeholder="Purpose, e.g. For college application">
                        <button class="btn btn-primary btn-sm w-full">Send request</button>
                    </div>
                </form>
            @empty
                <p class="card text-slate-500 sm:col-span-2">No documents can be requested online yet.</p>
            @endforelse
        </div>
        <aside class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-2 font-semibold">Your requests</h2>
            @forelse($requests as $r)
                <div class="border-b border-slate-100 py-2 text-sm last:border-0 dark:border-slate-800">
                    <p class="flex justify-between gap-2"><b>{{ $r->service->name }}</b><span class="text-xs">{{ \App\Models\ServiceRequest::STATUSES[$r->status] }}</span></p>
                    @if($r->charge && $r->charge->remaining() > 0 && $r->status !== 'declined')<a href="{{ route('portal.account') }}" class="text-xs font-semibold text-brand-700">Pay {{ $peso($r->charge->remaining()) }} →</a>@endif
                    @if($r->note)<p class="text-xs text-rose-700">{{ $r->note }}</p>@endif
                </div>
            @empty
                <p class="text-sm text-slate-500">None yet.</p>
            @endforelse
        </aside>
    </div>
</x-layouts.app>
