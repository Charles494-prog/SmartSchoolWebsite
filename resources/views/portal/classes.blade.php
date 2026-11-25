<x-layouts.app title="My classes">
    @include('portal._switch')
    @if($child)
        <h1 class="h1">My classes</h1>
        <p class="muted mb-6">Tap a class to see announcements, documents and work from the teacher.</p>
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @forelse($classes as $cs)
                @php $todo = ($todo[$cs->id] ?? collect())->count(); $slot = $cs->slots->sortBy('starts_at')->first(); @endphp
                <a href="{{ route('portal.class', [$cs] + request()->only('child')) }}" class="group overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg dark:border-slate-800 dark:bg-slate-900">
                    <div class="relative bg-gradient-to-br {{ \App\Support\Classwork::color($cs, auth()->user()) }} p-5 text-white">
                        <div class="text-xl font-bold group-hover:underline">{{ $cs->subject->name }}</div>
                        <div class="text-sm text-white/85">{{ $cs->teacher?->name }}</div>
                        @if($slot)<div class="mt-3 text-xs text-white/80">{{ ampm(substr($slot->starts_at, 0, 5)) }}–{{ ampm(substr($slot->ends_at, 0, 5)) }}</div>@endif
                        @if($todo)<span class="absolute top-4 right-4 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-900">{{ $todo }} to do</span>@endif
                    </div>
                    <div class="px-5 py-3 text-sm text-slate-500">{{ $todo ? $todo.' '.\Illuminate\Support\Str::plural('task', $todo).' due' : 'All caught up' }}</div>
                </a>
            @empty
                <p class="card muted">No classes yet.</p>
            @endforelse
        </div>
    @endif
</x-layouts.app>
