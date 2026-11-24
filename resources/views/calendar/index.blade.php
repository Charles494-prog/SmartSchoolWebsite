<x-layouts.app title="School calendar">
    <div class="mb-4 flex flex-wrap items-center gap-2">
        <h1 class="h1 flex-1">{{ $month->format('F Y') }}</h1>
        <a href="?month={{ $month->copy()->subMonth()->format('Y-m') }}" class="btn btn-sm" aria-label="Previous month"><x-icon name="chevron-left" class="h-4 w-4" /></a>
        <a href="?month={{ now()->format('Y-m') }}" class="btn btn-sm">Today</a>
        <a href="?month={{ $month->copy()->addMonth()->format('Y-m') }}" class="btn btn-sm" aria-label="Next month"><x-icon name="chevron" class="h-4 w-4" /></a>
        @if(auth()->user()->hasRole('admin', 'registrar'))<a href="{{ route('crud.create', 'events') }}" class="btn btn-primary btn-sm">+ Event</a>@endif
    </div>
    @php $colors = ['holiday' => 'bg-red-100 text-red-800', 'exam' => 'bg-amber-100 text-amber-800', 'meeting' => 'bg-blue-100 text-blue-800', 'event' => 'bg-green-100 text-green-800']; @endphp
    <div class="hidden grid-cols-7 gap-px overflow-hidden rounded-xl border bg-slate-200 md:grid">
        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d)<div class="bg-slate-50 p-2 text-center text-xs font-semibold">{{ $d }}</div>@endforeach
        @for($d = $start->copy(); $d <= $end; $d->addDay())
            <div class="min-h-24 bg-white p-1 {{ $d->month !== $month->month ? 'opacity-40' : '' }} {{ $d->isToday() ? 'ring-2 ring-brand-500' : '' }}">
                <div class="text-xs font-semibold">{{ $d->day }}</div>
                @foreach($events->filter(fn($e) => $d->between($e->starts_on, $e->ends_on ?? $e->starts_on)) as $e)
                    <div class="mt-1 truncate rounded px-1 text-[11px] {{ $colors[$e->type] ?? '' }}" title="{{ $e->title }}">{{ $e->title }}</div>
                @endforeach
            </div>
        @endfor
    </div>
    <div class="space-y-2 md:hidden">
        @forelse($events->filter(fn($e) => $e->starts_on->month === $month->month || ($e->ends_on && $e->ends_on->month === $month->month)) as $e)
            <div class="card p-3"><b>{{ $e->starts_on->format('M d') }}{{ $e->ends_on ? '–'.$e->ends_on->format('M d') : '' }}</b> · {{ $e->title }} <span class="badge {{ $colors[$e->type] ?? '' }}">{{ \App\Models\Event::TYPES[$e->type] ?? $e->type }}</span></div>
        @empty <p class="card muted">No events this month.</p> @endforelse
    </div>
</x-layouts.app>
