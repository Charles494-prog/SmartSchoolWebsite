<x-layouts.app title="My classes">
    <div class="mb-6 flex flex-wrap items-end gap-3">
        <div class="mr-auto">
            <h1 class="h1">My classes</h1>
            <p class="muted">One card per subject you teach. Open a card to post announcements, documents and classwork, check work, and see the gradebook.</p>
        </div>
    </div>

    @if($classes->isEmpty())
        <div class="card py-12 text-center">
            <p class="text-lg font-semibold">No classes yet</p>
            <p class="muted mt-1">When the registrar assigns you a subject in Classes &amp; sections, its card appears here.</p>
        </div>
    @else
        <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($classes as $cs)
                @php $slot = $cs->slots->sortBy('starts_at')->first(); $due = $next[$cs->id] ?? null; $review = $toReview[$cs->id] ?? 0; @endphp
                <a href="{{ route('assignments.class', $cs) }}" class="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg focus-visible:outline-2 focus-visible:outline-offset-2 dark:border-slate-800 dark:bg-slate-900">
                    <div class="relative bg-gradient-to-br {{ \App\Support\Classwork::color($cs) }} p-5 text-white">
                        <div class="text-xl font-bold leading-tight group-hover:underline">{{ $cs->subject->name }}</div>
                        <div class="mt-0.5 text-sm text-white/85">{{ $cs->section->label() }}</div>
                        <div class="mt-3 text-xs text-white/80">{{ $cs->teacher?->name }}@if($slot) · {{ ampm(substr($slot->starts_at, 0, 5)) }}–{{ ampm(substr($slot->ends_at, 0, 5)) }}@endif</div>
                        @if($review)
                            <span class="absolute top-4 right-4 rounded-full bg-white px-2.5 py-1 text-xs font-bold text-slate-900 shadow">{{ $review }} to check</span>
                        @endif
                    </div>
                    <div class="flex flex-1 flex-col gap-1 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Next due</p>
                        @if($due)
                            <p class="font-medium text-slate-900 dark:text-white">{{ $due->title }}</p>
                            <p class="text-sm text-slate-500">{{ $due->due_at->format('D, M d · g:i A') }}</p>
                        @else
                            <p class="text-sm text-slate-500">Nothing due – post something new.</p>
                        @endif
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 px-5 py-3 text-sm text-slate-500 dark:border-slate-800">
                        <span>{{ $cs->assignments_count }} classwork</span>
                        <span class="font-medium text-brand-600 dark:text-brand-400">Open →</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.app>
