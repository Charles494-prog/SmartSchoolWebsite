<x-layouts.app title="Payment methods">
    <div class="mb-6"><h1 class="h1">Payment methods</h1><p class="muted">Where families can pay online. They see these on their Payments page, pay through the app or bank, and upload a screenshot for the cashier to approve.</p></div>
    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="space-y-3">
            @forelse($channels as $c)
                <div class="flex flex-wrap items-start gap-4 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ edit: false }">
                    @if($c->qr_path)<img src="{{ route('channels.qr', $c) }}" alt="QR code for {{ $c->name }}" class="h-24 w-24 rounded-lg border border-slate-200 bg-white object-contain p-1">@else<span class="flex h-24 w-24 items-center justify-center rounded-lg bg-slate-100 text-xs text-slate-400 dark:bg-slate-800">No QR</span>@endif
                    <div class="min-w-0 flex-1">
                        <p class="font-semibold">{{ $c->name }} <span class="ml-1 rounded-full px-2 py-0.5 text-xs {{ $c->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $c->is_active ? 'Shown to families' : 'Hidden' }}</span></p>
                        <p class="text-sm text-slate-500">{{ \App\Models\Payment::METHODS[$c->kind] ?? $c->kind }}</p>
                        <p class="mt-1 text-sm">{{ $c->account_name }} @if($c->account_number)· <span class="font-mono">{{ $c->account_number }}</span>@endif</p>
                        @if($c->instructions)<p class="mt-1 text-sm text-slate-500">{{ $c->instructions }}</p>@endif
                        <div class="mt-3 flex gap-2">
                            <button type="button" class="btn btn-sm" x-on:click="edit = !edit">Edit</button>
                            <form method="post" action="{{ route('channels.destroy', $c) }}" x-on:submit="if (!confirm('Remove {{ $c->name }}?')) $event.preventDefault()">@csrf @method('DELETE')<button class="btn btn-sm text-rose-600">Remove</button></form>
                        </div>
                        <form x-show="edit" x-cloak method="post" action="{{ route('channels.update', $c) }}" enctype="multipart/form-data" class="mt-4 space-y-2">@csrf
                            @include('finance._channel-fields', ['c' => $c])
                            <button class="btn btn-primary btn-sm">Save</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="card py-10 text-center text-slate-500">No payment methods yet. Add GCash, Maya or your bank account on the right.</div>
            @endforelse
        </div>
        <form method="post" action="{{ route('channels.store') }}" enctype="multipart/form-data" class="card h-fit space-y-3">@csrf
            <h2 class="font-semibold">Add a payment method</h2>
            @include('finance._channel-fields', ['c' => null])
            <button class="btn btn-primary w-full">Add</button>
        </form>
    </div>
</x-layouts.app>
