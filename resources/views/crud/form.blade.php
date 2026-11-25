@php
    // Fields can carry a 'group' (section heading). Checkboxes become switches; long text spans the full width.
    $groups = collect($r['fields'])->groupBy(fn ($f) => $f['group'] ?? 'Details');
    $sum = $r['sum'] ?? null; // ['fields' => [...], 'total' => 100, 'label' => '...']
@endphp
<x-layouts.app :title="($model ? 'Edit ' : 'Add ').$r['singular']">
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('crud.index', $slug) }}" class="mb-3 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900"><x-icon name="arrow-left" class="h-4 w-4" />{{ $r['title'] }}</a>
        <h1 class="h1">{{ $model ? 'Edit' : 'Add' }} {{ $r['singular'] }}</h1>
        <p class="muted mt-1 mb-6">Fields marked <span class="text-rose-600">*</span> are required.</p>

        <form method="post" action="{{ $model ? route('crud.update', [$slug, $model->id]) : route('crud.store', $slug) }}" class="space-y-5"
              x-data="{ busy: false, vals: {} @if($sum), get total() { return {{ collect($sum['fields'])->map(fn ($n) => "(Number(this.vals['$n']) || 0)")->implode(' + ') }} }@endif }"
              x-on:submit="busy = true">
            @csrf
            @if($model) @method('put') @endif

            @foreach($groups as $group => $fields)
                <section class="card p-0">
                    @if($groups->count() > 1)<div class="border-b border-slate-100 px-6 py-4 dark:border-white/10"><h2 class="h2">{{ $group }}</h2></div>@endif
                    <div class="grid gap-x-5 gap-y-4 px-6 py-5 sm:grid-cols-2">
                        @foreach($fields as $f)
                            @php
                                $type = $f['type'] ?? 'text';
                                $value = $model ? data_get($model, $f['name']) : (isset($f['default']) ? $f['default']() : ($type === 'checkbox' ? true : null));
                                if ($value instanceof \Carbon\CarbonInterface) { $value = $type === 'datetime-local' ? $value->format('Y-m-d\TH:i') : $value->format('Y-m-d'); }
                                $rules = $f['rules'] ?? '';
                                $required = is_string($rules) && str_contains($rules, 'required') && ! str_contains($rules, 'required_');
                                $inSum = $sum && in_array($f['name'], $sum['fields'], true);
                            @endphp
                            @if($model && ($f['locked'] ?? false))
                                <div><div class="label">{{ $f['label'] }}</div><div class="flex min-h-11 items-center gap-2 rounded-xl bg-slate-50 px-3.5 text-sm text-slate-700 ring-1 ring-slate-200"><x-icon name="key" class="h-4 w-4 text-slate-400" /> {{ isset($f['options']) ? ($f['options']()[$value] ?? $value) : $value }}</div><p class="mt-1 text-xs text-slate-500">Can't be changed – add a new record instead.</p></div>
                                @continue
                            @endif
                            @if($type === 'checkbox')
                                <div class="sm:col-span-2"><x-toggle :name="$f['name']" :label="$f['label']" :checked="(bool) $value" :help="$f['help'] ?? null" /></div>
                            @else
                                <div class="{{ $type === 'textarea' ? 'sm:col-span-2' : '' }}" @if($inSum) x-init="vals['{{ $f['name'] }}'] = $el.querySelector('input')?.value" x-on:input="vals['{{ $f['name'] }}'] = $event.target.value" @endif>
                                    <x-field :name="$f['name']" :label="$f['label']" :type="$type" :value="$value" :required="$required"
                                             :options="isset($f['options']) ? $f['options']() : []" :help="$f['help'] ?? null" :placeholder="$f['placeholder'] ?? null" :step="$f['step'] ?? null" />
                                </div>
                            @endif
                        @endforeach
                        @if($sum && collect($fields)->pluck('name')->intersect($sum['fields'])->isNotEmpty())
                            <div class="flex min-h-11 items-center justify-between rounded-xl px-4 text-sm font-medium sm:col-span-2"
                                 :class="total === {{ $sum['total'] }} ? 'bg-emerald-50 text-emerald-800' : 'bg-amber-50 text-amber-900'" role="status" aria-live="polite">
                                <span>{{ $sum['label'] ?? 'Total' }}</span>
                                <span><span x-text="total"></span> / {{ $sum['total'] }}<span x-show="total !== {{ $sum['total'] }}" x-cloak> – must equal {{ $sum['total'] }}</span></span>
                            </div>
                        @endif
                    </div>
                </section>
            @endforeach

            <div class="sticky bottom-0 z-10 -mx-4 flex items-center justify-end gap-2 border-t border-slate-200 bg-white/90 px-4 py-3 backdrop-blur sm:mx-0 sm:rounded-2xl sm:border sm:shadow-sm dark:border-white/10 dark:bg-slate-900/90">
                <a href="{{ route('crud.index', $slug) }}" class="btn min-h-11">Cancel</a>
                <button class="btn btn-primary min-h-11" :disabled="busy"><span x-text="busy ? 'Saving…' : 'Save {{ $r['singular'] }}'">Save {{ $r['singular'] }}</span></button>
            </div>
        </form>

        @if($model)
            <section class="mt-8 rounded-2xl p-5 ring-1 ring-rose-200 dark:ring-rose-500/30">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1"><h2 class="text-sm font-semibold text-slate-900">Delete this {{ $r['singular'] }}</h2><p class="text-sm text-slate-500">This cannot be undone. It is recorded in the activity log.</p></div>
                    <form method="post" action="{{ route('crud.destroy', [$slug, $model->id]) }}" x-data x-on:submit="if (!confirm('Delete this {{ $r['singular'] }}? This cannot be undone.')) $event.preventDefault()">
                        @csrf @method('delete')
                        <button class="btn min-h-11 text-rose-600 ring-rose-200 hover:bg-rose-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                    </form>
                </div>
            </section>
        @endif
    </div>
</x-layouts.app>
