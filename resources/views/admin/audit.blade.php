<x-layouts.app title="Audit log">
    <h1 class="h1 mb-1">Audit log</h1>
    <p class="muted mb-4">Every create, change and delete – including each grade/score change with its old and new value.</p>
    <form class="mb-4 flex flex-wrap gap-2">
        <select name="auditable_type" class="input max-w-[12rem]"><option value="">All records</option>@foreach($types as $t)<option @selected(request('auditable_type') === $t)>{{ $t }}</option>@endforeach</select>
        <select name="action" class="input max-w-[10rem]"><option value="">All actions</option>@foreach(['created', 'updated', 'deleted', 'login', 'export', 'download', 'reminders'] as $a)<option @selected(request('action') === $a)>{{ $a }}</option>@endforeach</select>
        <select name="user_id" class="input max-w-[12rem]"><option value="">All users</option>@foreach($users as $k => $v)<option value="{{ $k }}" @selected(request('user_id') == $k)>{{ $v }}</option>@endforeach</select>
        <input name="id" value="{{ request('id') }}" class="input w-24" placeholder="Record ID">
        <button class="btn">Filter</button>
    </form>
    <div class="card overflow-x-auto p-0">
        <table class="table text-xs">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Record</th><th>Changes</th><th>IP</th></tr></thead>
            <tbody>
            @forelse($logs as $l)
                <tr>
                    <td class="whitespace-nowrap">{{ $l->created_at->format('M d, Y g:i:s A') }}</td>
                    <td>{{ $l->user?->name ?? 'system' }}</td>
                    <td><span class="badge {{ $l->action === 'deleted' ? 'bg-red-100 text-red-800' : ($l->action === 'updated' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100') }}">{{ $l->action }}</span></td>
                    <td class="whitespace-nowrap">{{ $l->auditable_type }} {{ $l->auditable_id ? '#'.$l->auditable_id : '' }} {{ $l->description }}</td>
                    <td class="max-w-xl">
                        @if($l->action === 'updated')
                            @foreach($l->new_values ?? [] as $k => $v)<div><b>{{ $k }}</b>: <span class="text-red-700 line-through">{{ is_scalar($l->old_values[$k] ?? null) ? $l->old_values[$k] : json_encode($l->old_values[$k] ?? null) }}</span> → <span class="text-green-700">{{ is_scalar($v) ? $v : json_encode($v) }}</span></div>@endforeach
                        @else
                            <span class="text-slate-500">{{ \Illuminate\Support\Str::limit(json_encode($l->new_values ?: $l->old_values, JSON_UNESCAPED_UNICODE), 160) }}</span>
                        @endif
                    </td>
                    <td>{{ $l->ip }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-8 text-center text-slate-500">Nothing logged yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</x-layouts.app>
