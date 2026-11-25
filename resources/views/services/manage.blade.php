<x-layouts.app title="Document requests">
    @php
        $peso = fn ($v) => \App\Services\Billing::peso($v);
        $steps = ['requested' => 'Requested', 'processing' => 'Being prepared', 'ready' => 'Papers ready', 'released' => 'Released'];
        $order = array_keys($steps);
        $tab = request('tab') === 'list' ? 'list' : 'requests';
    @endphp
    <x-page-header title="Document requests" subtitle="Students and parents request documents from their portal. Any fee goes on their balance; release the document once Finance confirms payment." />

    <div x-data="{ tab: '{{ $tab }}' }">
        <div class="mb-5 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'requests'" class="min-h-11 rounded-lg px-4 font-medium" :class="tab === 'requests' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300'" x-on:click="tab = 'requests'">Requests</button>
            <button type="button" role="tab" :aria-selected="tab === 'list'" class="min-h-11 rounded-lg px-4 font-medium" :class="tab === 'list' ? 'bg-white shadow-sm dark:bg-slate-700' : 'text-slate-600 dark:text-slate-300'" x-on:click="tab = 'list'">Documents &amp; fees <span class="opacity-60">{{ $services->count() }}</span></button>
        </div>

        {{-- ================= Requests ================= --}}
        <div x-show="tab === 'requests'">
            <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                @foreach(\App\Models\ServiceRequest::STATUSES as $k => $label)
                    <a href="{{ route('services.manage', ['status' => $k]) }}" @if($status === $k) aria-current="page" @endif
                       class="flex min-h-14 items-center justify-between rounded-xl border px-4 py-2 text-sm {{ $status === $k ? 'border-brand-600 bg-brand-50 font-semibold text-brand-800 dark:bg-brand-500/10 dark:text-brand-200' : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300' }}">
                        <span>{{ $k === 'ready' ? 'Papers ready' : $label }}</span><span class="text-lg font-bold tabular-nums">{{ $counts[$k] ?? 0 }}</span>
                    </a>
                @endforeach
            </div>
            <form method="get" class="mb-4 flex gap-2"><input type="hidden" name="status" value="{{ $status }}">
                <label class="relative flex-1 sm:max-w-sm"><span class="sr-only">Search student</span><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input name="q" value="{{ $q }}" class="input min-h-11 pl-9" placeholder="Search student name or ID"></label>
                <button class="btn min-h-11">Search</button>
                @if($q !== '')<a href="{{ route('services.manage', ['status' => $status]) }}" class="btn min-h-11">Clear</a>@endif
            </form>

            <ul class="space-y-3">
                @forelse($requests as $r)
                    @php $paid = ! $r->charge || $r->charge->remaining() <= 0; $at = array_search($r->status, $order, true); @endphp
                    <li class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 dark:border-slate-800 dark:bg-slate-900" x-data="{ no: false }">
                        <div class="flex flex-wrap items-start gap-3">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-white/5 dark:text-slate-300"><x-icon name="file" class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1 basis-56">
                                <p class="font-semibold break-words">{{ $r->service->name }}{{ $r->copies > 1 ? ' ×'.$r->copies : '' }}</p>
                                <p class="text-sm break-words text-slate-700 dark:text-slate-300">{{ $r->student->full_name }} <span class="text-slate-500">· {{ $r->student->student_no }}</span></p>
                                <p class="text-xs text-slate-500">{{ $r->purpose ?: 'No purpose given' }} · requested {{ $r->created_at->format('M j, Y') }} ({{ $r->created_at->diffForHumans() }})</p>
                            </div>
                            <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-semibold {{ $paid ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-300' : 'bg-amber-50 text-amber-900 dark:bg-amber-500/10 dark:text-amber-200' }}">
                                <x-icon name="{{ $paid ? 'check-circle' : 'clock' }}" class="h-3.5 w-3.5" />{{ $r->charge ? ($paid ? 'Paid' : $peso($r->charge->remaining()).' unpaid') : 'Free' }}</span>
                        </div>

                        @if($r->status !== 'declined')
                            {{-- progress --}}
                            <ol class="mt-4 grid grid-cols-4 gap-1 text-[11px] sm:text-xs" aria-label="Progress">
                                @foreach($steps as $k => $label)
                                    @php $i = array_search($k, $order, true); @endphp
                                    <li class="flex flex-col gap-1.5" @if($i === $at) aria-current="step" @endif>
                                        <span class="h-1.5 rounded-full {{ $i <= $at ? 'bg-brand-600' : 'bg-slate-200 dark:bg-slate-700' }}"></span>
                                        <span class="{{ $i === $at ? 'font-semibold text-slate-900 dark:text-white' : ($i < $at ? 'text-slate-600 dark:text-slate-400' : 'text-slate-400') }}">{{ $label }}</span>
                                    </li>
                                @endforeach
                            </ol>
                        @else
                            <p class="mt-3 rounded-lg bg-rose-50 p-2.5 text-sm text-rose-800 dark:bg-rose-500/10 dark:text-rose-200">Not accepted: “{{ $r->note }}”</p>
                        @endif

                        @if(in_array($r->status, ['requested', 'processing', 'ready'], true))
                            <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-4 dark:border-slate-800">
                                <form method="post" action="{{ route('services.requests.update', $r) }}" class="flex flex-wrap items-center gap-2">@csrf
                                    <span x-show="!no" class="flex flex-wrap gap-2">
                                        @if($r->status === 'requested')<button name="status" value="processing" class="btn btn-primary min-h-11">Start preparing</button>@endif
                                        @if($r->status === 'processing')<button name="status" value="ready" class="btn btn-primary min-h-11">Mark papers ready</button>@endif
                                        @if($r->status === 'ready' && $paid)<button name="status" value="released" class="btn btn-primary min-h-11">Release to student</button>@endif
                                        @if($r->status === 'requested')<button name="status" value="ready" class="btn min-h-11">Skip to papers ready</button>@endif
                                        @if($r->status === 'processing' && $paid)<button name="status" value="released" class="btn min-h-11">Release now</button>@endif
                                    </span>
                                    @if(! $r->charge || $r->charge->paid() == 0)
                                        <button type="button" class="btn min-h-11 text-rose-700" x-show="!no" x-on:click="no = true">Decline…</button>
                                        <span x-show="no" x-cloak class="flex flex-wrap gap-2"><label class="sr-only" for="note-{{ $r->id }}">Reason</label><input id="note-{{ $r->id }}" name="note" class="input min-h-11 w-64" placeholder="Why? The student will see this" x-bind:required="no">
                                            <button name="status" value="declined" class="btn min-h-11 text-rose-700">Decline request</button><button type="button" class="btn min-h-11" x-on:click="no = false">Cancel</button></span>
                                    @endif
                                </form>
                                @if(in_array($r->status, ['processing', 'ready'], true))
                                    <form method="post" action="{{ route('services.requests.notify', $r) }}" class="sm:ml-auto" x-show="!no">@csrf
                                        <button class="btn min-h-11"><x-icon name="bell" class="h-4 w-4" />
                                            {{ $r->status === 'processing' ? 'Tell them it’s being prepared' : ($paid ? 'Tell them to come get it' : 'Tell them it’s ready – pay first') }}</button>
                                    </form>
                                @endif
                            </div>
                            @if($r->status === 'ready' && ! $paid)<p class="mt-2 text-xs text-slate-600 dark:text-slate-400">You can release it once Finance confirms the payment. You’ll get a notification.</p>@endif
                        @endif
                    </li>
                @empty
                    <li><x-empty title="No {{ strtolower($k = $status === 'ready' ? 'papers ready' : \App\Models\ServiceRequest::STATUSES[$status]) }} requests" text="{{ $q !== '' ? 'No match for “'.$q.'”.' : 'Requests show up here as they move along.' }}" /></li>
                @endforelse
            </ul>
            <div class="mt-4">{{ $requests->links() }}</div>
        </div>

        {{-- ================= Documents & fees ================= --}}
        <div x-show="tab === 'list'" x-cloak class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_340px]">
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                <div class="hidden grid-cols-[minmax(0,1fr)_120px_90px_70px_80px] gap-3 border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500 md:grid dark:border-slate-800">
                    <span>Document</span><span>Fee</span><span>Days</span><span>Offered</span><span></span>
                </div>
                @forelse($services as $s)
                    <form method="post" action="{{ route('services.update', $s) }}" class="grid gap-3 border-b border-slate-100 p-4 last:border-0 md:grid-cols-[minmax(0,1fr)_120px_90px_70px_80px] md:items-center dark:border-slate-800">@csrf
                        <div class="min-w-0">
                            <label class="sr-only" for="sn-{{ $s->id }}">Name</label><input id="sn-{{ $s->id }}" name="name" value="{{ $s->name }}" class="input min-h-11" required maxlength="120">
                            <label class="sr-only" for="sd-{{ $s->id }}">Description</label><input id="sd-{{ $s->id }}" name="description" value="{{ $s->description }}" class="input mt-1.5 min-h-9 text-xs" placeholder="Short description (optional)" maxlength="255">
                            <p class="mt-1 text-xs text-slate-500">{{ $s->requests_count }} {{ str('request')->plural($s->requests_count) }} so far</p>
                        </div>
                        <label class="flex items-center gap-1 text-sm"><span class="md:sr-only w-16 text-slate-500">Fee</span>₱<input name="fee" type="number" step="0.01" min="0" value="{{ $s->fee }}" class="input min-h-11 w-full"></label>
                        <label class="flex items-center gap-1 text-sm"><span class="md:sr-only w-16 text-slate-500">Days</span><input name="days" type="number" min="0" max="120" value="{{ $s->days }}" class="input min-h-11 w-full"></label>
                        <label class="flex min-h-11 items-center gap-2 text-sm"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="h-5 w-5 accent-brand-600" @checked($s->is_active)><span class="md:sr-only">Offered to students</span></label>
                        <button class="btn min-h-11">Save</button>
                    </form>
                @empty
                    <x-empty title="No documents yet" text="Add the first one on the right." />
                @endforelse
            </div>
            <form method="post" action="{{ route('services.store') }}" class="card h-fit space-y-3">@csrf
                <h2 class="h2">Add a document or service</h2>
                <x-field name="name" label="Name" placeholder="e.g. Certificate of Grades" required />
                <x-field name="description" label="Short description" placeholder="Optional" />
                <div class="grid grid-cols-2 gap-2"><x-field name="fee" label="Fee (₱)" type="number" step="0.01" min="0" required /><x-field name="days" label="Days to prepare" type="number" min="0" value="3" required /></div>
                <button class="btn btn-primary min-h-11 w-full">Add document</button>
            </form>
        </div>
    </div>
</x-layouts.app>
