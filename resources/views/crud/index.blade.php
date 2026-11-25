<x-layouts.app :title="$r['title']">
    <x-page-header :title="$r['title']"><a href="{{ route('crud.create', $slug) }}" class="btn btn-primary min-h-11"><x-icon name="plus" class="h-4 w-4" /> Add {{ $r['singular'] }}</a></x-page-header>
    @isset($r['help'])<p class="mb-4 rounded-xl bg-brand-50 px-4 py-3 text-sm text-brand-900">{{ $r['help'] }}</p>@endisset
    @isset($r['panel'])@include($r['panel'])@endisset
    <x-filters search="Search…" :filters="collect($r['filters'] ?? [])->map(fn ($f) => ['label' => $f['label'], 'options' => $f['options']()])->all()" />

    @php $pageIds = $rows->pluck('id')->values(); @endphp
    <div x-data="{ sel: [], ids: @js($pageIds), busy: false,
                   get all() { return this.ids.length > 0 && this.sel.length === this.ids.length },
                   toggleAll() { this.sel = this.all ? [] : [...this.ids] } }">

        {{-- Selection bar --}}
        <div x-show="sel.length" x-cloak x-transition.opacity.duration.200ms
             class="sticky top-20 z-20 mb-3 flex flex-wrap items-center gap-3 rounded-2xl bg-slate-900 px-4 py-2.5 text-sm text-white shadow-lg dark:bg-slate-800">
            <span class="font-medium"><span x-text="sel.length"></span> selected</span>
            <button type="button" class="min-h-11 rounded-lg px-3 text-slate-300 hover:bg-white/10 hover:text-white" x-on:click="sel = []">Clear</button>
            <form method="post" action="{{ route('crud.bulk-destroy', $slug) }}" class="ml-auto"
                  x-on:submit="if (!confirm('Delete ' + sel.length + ' selected {{ \Illuminate\Support\Str::plural($r['singular']) }}? This cannot be undone and is recorded in the audit log.')) { $event.preventDefault() } else { busy = true }">
                @csrf
                <template x-for="id in sel" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button class="btn btn-danger min-h-11" :disabled="busy"><x-icon name="trash" class="h-4 w-4" /> <span x-text="busy ? 'Deleting…' : 'Delete selected'">Delete selected</span></button>
            </form>
        </div>

        <div class="card overflow-x-auto p-0">
            <table class="table">
                <thead><tr>
                    <th class="w-12">
                        @if($rows->count())
                            <input type="checkbox" class="h-4 w-4 cursor-pointer rounded" :checked="all" :indeterminate="sel.length > 0 && !all" x-on:change="toggleAll()" aria-label="Select all on this page">
                        @endif
                    </th>
                    @foreach($r['columns'] as $c => $label)<th>{{ $label }}</th>@endforeach<th class="w-28"><span class="sr-only">Actions</span></th>
                </tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr :class="sel.includes({{ $row->id }}) && 'bg-brand-50/60 dark:bg-white/5'">
                        <td class="w-12"><input type="checkbox" class="h-4 w-4 cursor-pointer rounded" value="{{ $row->id }}" x-model.number="sel" aria-label="Select row"></td>
                        @foreach($r['columns'] as $c => $label)
                            @php $v = data_get($row, $c); @endphp
                            <td>
                                @if(is_bool($v)){{ $v ? 'Yes' : 'No' }}
                                @elseif($v instanceof \Carbon\CarbonInterface){{ $v->format(str_contains($c, '_at') ? 'M j, Y g:i A' : 'M j, Y') }}
                                @elseif($c === 'role'){{ \App\Support\Role::label($v) }}
                                @elseif(is_float($v) || in_array($c, ['amount', 'monthly_salary'])){{ number_format((float) $v, 2) }}
                                @else{{ \Illuminate\Support\Str::limit((string) $v, 60) }}@endif
                            </td>
                        @endforeach
                        <td class="whitespace-nowrap text-right">
                            <div class="inline-flex items-center gap-1">
                                <a href="{{ route('crud.edit', [$slug, $row->id]) }}" class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100 hover:text-brand-600" title="Edit" aria-label="Edit"><x-icon name="pencil" class="h-4 w-4" /></a>
                                <form method="post" action="{{ route('crud.destroy', [$slug, $row->id]) }}" x-on:submit="if (!confirm('Delete this {{ $r['singular'] }}? This cannot be undone.')) $event.preventDefault()">
                                    @csrf @method('delete')
                                    <button class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="Delete" aria-label="Delete"><x-icon name="trash" class="h-4 w-4" /></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="99">
                        @if(request()->filled('q') || collect(array_keys($r['filters'] ?? []))->contains(fn ($k) => request()->filled($k)))
                            <x-empty icon="search" title="Nothing matches" text="Try another word or remove a filter."><a href="{{ route('crud.index', $slug) }}" class="btn min-h-11">Clear search</a></x-empty>
                        @else
                            <x-empty :icon="$r['icon'] ?? 'sparkles'" title="No {{ \Illuminate\Support\Str::plural($r['singular']) }} yet" :text="$r['empty'] ?? null"
                                     action="Add {{ $r['singular'] }}" :href="route('crud.create', $slug)" :steps="$r['steps'] ?? []" />
                        @endif
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
</x-layouts.app>
