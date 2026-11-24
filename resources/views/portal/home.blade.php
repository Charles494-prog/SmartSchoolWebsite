<x-layouts.app title="Home">
    @include('portal._switch')
    @if($child)
        <h1 class="h1">{{ auth()->user()->role === 'parent' ? 'Welcome, '.auth()->user()->name : 'Welcome, '.$child->first_name }}</h1>
        <p class="muted mb-4">{{ $child->full_name }} · {{ $enrollment?->gradeLevel?->name }} {{ $enrollment?->section ? '– '.$enrollment->section->name : '' }}</p>
        <div class="grid gap-3 sm:grid-cols-3">
            <x-stat icon="award" label="General average" :value="$report['general_average'] ?? 'No grades yet'" tone="brand" :href="route('portal.grades')" />
            <x-stat icon="check-square" :label="'Absent this month · '.$attendance->where('status', 'late')->count().' late'" :value="$attendance->where('status', 'absent')->count()" :tone="$attendance->where('status', 'absent')->count() ? 'red' : 'green'" :href="route('portal.attendance')" />
            <x-stat icon="wallet" label="Balance" :value="$balance === null ? '—' : \App\Services\Billing::peso($balance)" :tone="$balance > 0 ? 'red' : 'green'" :href="route('portal.account')" />
        </div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <div class="card">
                <h2 class="h2 mb-2">Announcements</h2>
                @forelse($announcements as $a)
                    <div class="border-b py-2 last:border-0"><div class="font-semibold">@if($a->is_pinned)<x-icon name="pin" class="mr-1 inline h-4 w-4 text-brand-500" />@endif{{ $a->title }}</div><div class="whitespace-pre-line text-sm">{{ $a->body }}</div><div class="muted text-xs">{{ $a->published_at->format('M d, Y') }}</div></div>
                @empty <p class="muted">No announcements.</p> @endforelse
            </div>
            <div class="space-y-4">
                <div class="card">
                    <h2 class="h2 mb-2">Upcoming</h2>
                    @forelse($events as $ev)<p class="text-sm"><b>{{ $ev->starts_on->format('M d') }}</b> · {{ $ev->title }} <span class="muted">({{ \App\Models\Event::TYPES[$ev->type] ?? $ev->type }})</span></p>@empty<p class="muted">Nothing scheduled.</p>@endforelse
                </div>
                <div class="card">
                    <h2 class="h2 mb-2">Notifications</h2>
                    @forelse($notifications as $n)<p class="text-sm {{ $n->read_at ? 'text-slate-500' : 'font-semibold' }}">{{ $n->data['title'] ?? '' }}: {{ $n->data['body'] ?? '' }}</p>@empty<p class="muted">None.</p>@endforelse
                </div>
            </div>
        </div>
    @endif
</x-layouts.app>
