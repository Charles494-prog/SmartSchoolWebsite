<x-layouts.app title="Schedule">
    @include('portal._switch')
    @if($child)
        <h1 class="h1 mb-4">Class schedule · {{ $enrollment?->section?->label() ?? 'no section yet' }}</h1>
        <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
            @foreach([1,2,3,4,5,6] as $d)
                <div class="card p-3">
                    <div class="mb-2 font-semibold">{{ \App\Models\ScheduleSlot::DAYS[$d] }}</div>
                    @forelse($slots[$d] ?? [] as $s)
                        <div class="mb-2 rounded-lg bg-brand-50 p-2 text-xs"><b>{{ ampm($s->starts_at) }}–{{ ampm($s->ends_at) }}</b><br>{{ $s->classSubject->subject->name }}<br><span class="text-slate-500">{{ $s->classSubject->teacher?->name }} · {{ $s->room }}</span></div>
                    @empty <p class="text-xs text-slate-400">—</p> @endforelse
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
