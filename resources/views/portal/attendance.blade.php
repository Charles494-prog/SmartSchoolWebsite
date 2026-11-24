<x-layouts.app title="Attendance">
    @include('portal._switch')
    @if($child)
        <h1 class="h1 mb-4">Attendance · {{ $child->first_name }}</h1>
        <div class="mb-4 flex flex-wrap gap-2">@foreach(\App\Models\AttendanceRecord::STATUSES as $k => $v)<span class="badge bg-white px-3 py-1 text-sm shadow">{{ $v }}: {{ $records->where('status', $k)->count() }}</span>@endforeach</div>
        <div class="card overflow-x-auto p-0">
            <table class="table">
                <thead><tr><th>Date</th><th>Status</th><th>Time in</th><th>Remarks</th></tr></thead>
                <tbody>
                @forelse($records as $r)<tr><td>{{ $r->date->format('D, M d, Y') }}</td><td><x-status :value="$r->status" /></td><td>{{ ampm($r->time_in) }}</td><td>{{ $r->remarks }}</td></tr>
                @empty<tr><td colspan="4" class="py-8 text-center text-slate-500">No records yet.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
