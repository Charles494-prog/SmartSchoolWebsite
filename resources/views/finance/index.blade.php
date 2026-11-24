<x-layouts.app title="Student accounts">
    <h1 class="h1 mb-4">Student accounts</h1>
    <div class="mb-4 grid gap-3 sm:grid-cols-4">
        <x-stat label="Total billed (this S.Y.)" :value="\App\Services\Billing::peso($billed)" />
        <x-stat label="Collected" :value="\App\Services\Billing::peso($collected)" tone="green" />
        <x-stat label="Collection rate" :value="$rate.'%'" tone="brand" />
        <x-stat label="Not yet assessed" :value="$unassessed" tone="amber" :href="route('finance.index', ['filter' => 'unassessed'])" />
    </div>
    <form class="mb-4 flex gap-2"><input name="q" value="{{ request('q') }}" class="input max-w-xs" placeholder="Search student…"><button class="btn">Search</button></form>
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr><th>Student</th><th>Grade & section</th><th>Plan</th><th class="text-right">Net</th><th class="text-right">Paid</th><th class="text-right">Balance</th><th></th></tr></thead>
            <tbody>
            @forelse($rows as $e)
                @php $a = $e->account; $paid = $a ? $a->payments->where('is_void', false)->sum('amount') : 0; @endphp
                <tr>
                    <td>{{ $e->student->full_name }}</td>
                    <td>{{ $e->gradeLevel->name }} {{ $e->section?->name }}</td>
                    <td>{{ $a ? $a->planName() : '—' }}</td>
                    <td class="text-right">{{ $a ? number_format($a->net_amount, 2) : '—' }}</td>
                    <td class="text-right">{{ number_format($paid, 2) }}</td>
                    <td class="text-right font-semibold {{ $a && $a->net_amount - $paid > 0 ? 'text-red-700' : 'text-green-700' }}">{{ $a ? number_format($a->net_amount - $paid, 2) : '' }}</td>
                    <td class="text-right"><a class="link" href="{{ route('finance.show', $e) }}">{{ $a ? 'Open' : 'Assess fees' }}</a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-8 text-center text-slate-500">No enrolled students for the current school year.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</x-layouts.app>
