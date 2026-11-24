<x-layouts.app title="Online applications">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <h1 class="h1 flex-1">Online applications</h1>
        <a href="{{ route('apply.create') }}" target="_blank" class="btn">Open public application form <x-icon name="external" class="h-4 w-4" /></a>
    </div>
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('applications.index') }}" class="btn btn-sm {{ request('status') ? '' : 'btn-primary' }}">All</a>
        @foreach(\App\Models\Application::STATUSES as $k => $v)
            <a href="{{ route('applications.index', ['status' => $k]) }}" class="btn btn-sm {{ request('status') === $k ? 'btn-primary' : '' }}">{{ $v }} ({{ $counts[$k] ?? 0 }})</a>
        @endforeach
        <form class="ml-auto"><input name="q" value="{{ request('q') }}" class="input" placeholder="Search name or reference…"></form>
    </div>
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Reference</th><th>Applicant</th><th>Grade level</th><th>Type</th><th>Submitted</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($apps as $a)
                <tr>
                    <td><a class="link" href="{{ route('applications.show', $a) }}">{{ $a->reference_no }}</a></td>
                    <td>{{ $a->full_name }}</td><td>{{ $a->gradeLevel->name }}</td><td>{{ ucfirst($a->applicant_type) }}</td>
                    <td>{{ $a->created_at->format('M d, Y') }}</td><td><x-status :value="$a->status" /></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">No applications yet. Share the public form link with families.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $apps->links() }}</div>
</x-layouts.app>
