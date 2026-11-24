<x-layouts.app :title="$r['title']">
    <x-page-header :title="$r['title']"><a href="{{ route('crud.create', $slug) }}" class="btn btn-primary"><x-icon name="plus" class="h-4 w-4" /> Add {{ $r['singular'] }}</a></x-page-header>
    @isset($r['help'])<p class="mb-4 rounded-lg bg-brand-50 px-4 py-3 text-sm text-brand-900">{{ $r['help'] }}</p>@endisset
    <x-filters search="Search…" :filters="collect($r['filters'] ?? [])->map(fn ($f) => ['label' => $f['label'], 'options' => $f['options']()])->all()" />
    <div class="card overflow-x-auto p-0">
        <table class="table">
            <thead><tr>@foreach($r['columns'] as $c => $label)<th>{{ $label }}</th>@endforeach<th></th></tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($r['columns'] as $c => $label)
                        @php $v = data_get($row, $c); @endphp
                        <td>
                            @if(is_bool($v)){{ $v ? 'Yes' : 'No' }}
                            @elseif($v instanceof \Carbon\CarbonInterface){{ $v->format(str_contains($c, '_at') ? 'M d, Y g:i A' : 'M d, Y') }}
                            @elseif($c === 'role'){{ \App\Support\Role::label($v) }}
                            @elseif(is_float($v) || in_array($c, ['amount', 'monthly_salary'])){{ number_format((float) $v, 2) }}
                            @else{{ \Illuminate\Support\Str::limit((string) $v, 60) }}@endif
                        </td>
                    @endforeach
                    <td class="whitespace-nowrap text-right"><a class="link" href="{{ route('crud.edit', [$slug, $row->id]) }}">Edit</a></td>
                </tr>
            @empty
                <tr><td colspan="99" class="py-8 text-center text-slate-500">Nothing here yet. Click “Add {{ $r['singular'] }}” to create the first one.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
