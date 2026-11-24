<x-layouts.app title="Overdue balances">
    <h1 class="h1 mb-1">Overdue balances</h1>
    <p class="muted mb-4">Installments due today or earlier that are not yet paid.</p>
    <form method="post" action="{{ route('finance.remind') }}" x-data>
        @csrf
        <div class="card overflow-x-auto p-0">
            <table class="table">
                <thead><tr><th><input type="checkbox" class="rounded" @change="$root.querySelectorAll('.pick').forEach(c => c.checked = $event.target.checked)"></th><th>Student</th><th>Grade & section</th><th>Guardian</th><th class="text-right">Overdue</th><th class="text-right">Balance</th></tr></thead>
                <tbody>
                @forelse($rows as $r)
                    @php $e = $r['a']->enrollment; @endphp
                    <tr>
                        <td><input type="checkbox" name="account_ids[]" value="{{ $r['a']->id }}" class="pick rounded"></td>
                        <td><a class="link" href="{{ route('finance.show', $e) }}">{{ $e->student->full_name }}</a></td>
                        <td>{{ $e->gradeLevel->name }} {{ $e->section?->name }}</td>
                        <td>{{ $e->student->guardians->first()?->name }} {{ $e->student->guardians->first()?->phone }}</td>
                        <td class="text-right font-semibold text-red-700">{{ number_format($r['overdue'], 2) }}</td>
                        <td class="text-right">{{ number_format($r['balance'], 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-8 text-center text-slate-500">No overdue balances.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->isNotEmpty())<button class="btn btn-primary mt-4">Send reminder to selected (portal + email)</button>@endif
    </form>
</x-layouts.app>
