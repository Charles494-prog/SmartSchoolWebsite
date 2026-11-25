<x-layouts.app title="Document requests">
    @php $peso = fn ($v) => \App\Services\Billing::peso($v); @endphp
    <div class="mb-5"><h1 class="h1">Document requests</h1><p class="muted">Students and parents request documents from their portal. A fee goes on their balance; release the document once finance confirms payment.</p></div>
    <div x-data="{ tab: 'requests' }">
        <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800">
            <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'requests' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'requests'">Requests</button>
            <button type="button" class="rounded-lg px-3 py-1.5 font-medium" :class="tab === 'list' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-500'" x-on:click="tab = 'list'">Documents & fees ({{ $services->count() }})</button>
        </div>
        <div x-show="tab === 'requests'">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach(\App\Models\ServiceRequest::STATUSES as $k => $label)
                    <a href="{{ route('services.manage', ['status' => $k]) }}" class="rounded-full border px-3 py-1 text-sm {{ $status === $k ? 'border-slate-900 bg-slate-900 text-white dark:bg-white dark:text-slate-900' : 'border-slate-200 text-slate-600 dark:border-slate-700 dark:text-slate-300' }}">{{ $label }} <span class="opacity-60">{{ $counts[$k] ?? 0 }}</span></a>
                @endforeach
            </div>
            <div class="rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse($requests as $r)
                        @php $paid = ! $r->charge || $r->charge->remaining() <= 0; @endphp
                        <li class="flex flex-wrap items-center gap-4 px-5 py-4" x-data="{ no: false }">
                            <div class="min-w-0 flex-1 basis-64"><p class="font-medium">{{ $r->service->name }}{{ $r->copies > 1 ? ' ×'.$r->copies : '' }} · {{ $r->student->full_name }}</p>
                                <p class="text-sm text-slate-600 dark:text-slate-400">{{ $r->purpose ?: 'No purpose given' }} · {{ $r->created_at->diffForHumans() }}</p></div>
                            <span class="text-sm {{ $paid ? 'text-emerald-700' : 'text-amber-700' }}">{{ $r->charge ? ($paid ? 'Paid' : $peso($r->charge->remaining()).' unpaid') : 'Free' }}</span>
                            <form method="post" action="{{ route('services.requests.update', $r) }}" class="flex flex-wrap items-center gap-2">@csrf
                                @if($r->status === 'requested')<button name="status" value="processing" class="btn btn-sm" x-show="!no">Start preparing</button>@endif
                                @if(in_array($r->status, ['requested', 'processing'], true))
                                    @if($paid)<button name="status" value="released" class="btn btn-primary btn-sm" x-show="!no">Release</button>@else<span class="text-xs text-slate-500">Release after payment</span>@endif
                                    <button type="button" class="btn btn-sm text-rose-600" x-show="!no" x-on:click="no = true">Decline…</button>
                                    <span x-show="no" x-cloak class="flex gap-2"><input name="note" class="input h-9 w-56" placeholder="Reason"><button name="status" value="declined" class="btn btn-sm text-rose-600">Send</button></span>
                                @endif
                                @if($r->note)<span class="text-xs text-rose-700">“{{ $r->note }}”</span>@endif
                            </form>
                        </li>
                    @empty
                        <li class="px-5 py-10 text-center text-slate-500">No requests here.</li>
                    @endforelse
                </ul>
            </div>
        </div>
        <div x-show="tab === 'list'" x-cloak class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="space-y-2">
                @foreach($services as $s)
                    <form method="post" action="{{ route('services.update', $s) }}" class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3 dark:border-slate-800 dark:bg-slate-900">@csrf
                        <input name="name" value="{{ $s->name }}" class="input h-10 min-w-48 flex-1">
                        <label class="flex items-center gap-1 text-sm">₱<input name="fee" type="number" step="0.01" min="0" value="{{ $s->fee }}" class="input h-10 w-24"></label>
                        <label class="flex items-center gap-1 text-sm"><input name="days" type="number" min="0" value="{{ $s->days }}" class="input h-10 w-16"> days</label>
                        <label class="flex items-center gap-1 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" @checked($s->is_active)> On</label>
                        <input type="hidden" name="description" value="{{ $s->description }}"><button class="btn btn-sm">Save</button>
                    </form>
                @endforeach
            </div>
            <form method="post" action="{{ route('services.store') }}" class="card h-fit space-y-2">@csrf
                <h2 class="font-semibold">Add a document or service</h2>
                <input name="name" class="input" placeholder="e.g. Certificate of Grades" required>
                <input name="description" class="input" placeholder="Short description (optional)">
                <div class="grid grid-cols-2 gap-2"><input name="fee" type="number" step="0.01" min="0" class="input" placeholder="Fee ₱" required><input name="days" type="number" min="0" value="3" class="input" placeholder="Days"></div>
                <button class="btn btn-primary w-full">Add</button>
            </form>
        </div>
    </div>
</x-layouts.app>
