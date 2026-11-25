<x-layouts.app title="Choose your section">
    <x-page-header title="Choose your section" :subtitle="$a ? 'Open until '.$a->deadline->format('M j, Y').' at '.ampm($a->deadline->format('H:i')).'. First come, first served – you can switch while seats remain.' : null" />
    @if(! $a)
        <div class="card"><x-empty icon="clock" title="Choosing is not open" text="Your school will tell you when you can choose a section. If you don’t choose, the school places you." /></div>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3" x-data x-init="setInterval(() => { if (!document.hidden) location.reload() }, 15000)">
            @foreach($a->sections()->with('adviser')->withCount(['enrollments' => fn ($q) => $q->where('status', 'enrolled')])->get() as $s)
                @php $left = max(0, $s->capacity - $s->enrollments_count); $mine = $e->section_id === $s->id; @endphp
                <form method="post" action="{{ route('portal.section-choose') }}" class="card flex flex-col gap-2 {{ $mine ? 'ring-2 ring-brand-600' : '' }}">@csrf<input type="hidden" name="section_id" value="{{ $s->id }}">
                    <p class="text-lg font-semibold break-words">{{ $s->name }}</p>
                    <p class="text-sm text-slate-600 dark:text-slate-400">Adviser: {{ $s->adviser?->name ?? 'To be announced' }}{{ $s->room ? ' · '.$s->room : '' }}</p>
                    <p class="text-sm font-medium {{ $left ? '' : 'text-rose-700' }}">{{ $left ? $left.' '.str('seat')->plural($left).' left' : 'Full' }}</p>
                    @if($mine)<p class="inline-flex items-center gap-1 text-sm font-semibold text-emerald-800"><x-icon name="check-circle" class="h-4 w-4" /> Your section</p>
                    @else<button class="btn btn-primary mt-auto min-h-11" @disabled(! $left)>{{ $left ? 'Choose '.$s->name : 'Full' }}</button>@endif
                </form>
            @endforeach
        </div>
    @endif
</x-layouts.app>
