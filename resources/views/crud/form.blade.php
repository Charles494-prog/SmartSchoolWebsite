<x-layouts.app :title="($model ? 'Edit ' : 'Add ').$r['singular']">
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('crud.index', $slug) }}" class="link"><x-icon name="arrow-left" class="mr-1 inline h-4 w-4" />{{ $r['title'] }}</a>
    </div>
    <div class="card max-w-3xl">
        <h1 class="h2 mb-4">{{ $model ? 'Edit' : 'Add' }} {{ $r['singular'] }}</h1>
        <form method="post" action="{{ $model ? route('crud.update', [$slug, $model->id]) : route('crud.store', $slug) }}" class="grid gap-4 sm:grid-cols-2">
            @csrf
            @if($model) @method('put') @endif
            @foreach($r['fields'] as $f)
                @php
                    $type = $f['type'] ?? 'text';
                    $value = $model ? data_get($model, $f['name']) : (isset($f['default']) ? $f['default']() : ($type === 'checkbox' ? true : null));
                    if ($value instanceof \Carbon\CarbonInterface) { $value = $type === 'datetime-local' ? $value->format('Y-m-d\TH:i') : $value->format('Y-m-d'); }
                    $rules = $f['rules'] ?? '';
                    $required = is_string($rules) && str_contains($rules, 'required');
                @endphp
                @if($model && ($f['locked'] ?? false))
                    <div><div class="label">{{ $f['label'] }}</div><div class="flex items-center gap-2 rounded-xl bg-slate-50 px-3.5 py-2.5 text-sm text-slate-700 ring-1 ring-slate-200"><x-icon name="key" class="h-4 w-4 text-slate-400" /> {{ isset($f['options']) ? ($f['options']()[$value] ?? $value) : $value }}</div><p class="mt-1 text-xs text-slate-500">Can't be changed – add a new record instead.</p></div>
                    @continue
                @endif
                <x-field :name="$f['name']" :label="$f['label']" :type="$type" :value="$value" :required="$required"
                         :options="isset($f['options']) ? $f['options']() : []" :help="$f['help'] ?? null" :placeholder="$f['placeholder'] ?? null"
                         :step="$f['step'] ?? null" class="{{ in_array($type, ['textarea', 'checkbox']) ? 'sm:col-span-2' : '' }}" />
            @endforeach
            <div class="flex gap-2 sm:col-span-2">
                <button class="btn btn-primary">Save</button>
                <a href="{{ route('crud.index', $slug) }}" class="btn">Cancel</a>
            </div>
        </form>
        @if($model)
            <form method="post" action="{{ route('crud.destroy', [$slug, $model->id]) }}" class="mt-6 border-t pt-4" x-data x-on:submit="if (!confirm('Delete this {{ $r['singular'] }}? This is recorded in the audit log.')) $event.preventDefault()">
                @csrf @method('delete')
                <button class="btn btn-danger btn-sm">Delete</button>
            </form>
        @endif
    </div>
</x-layouts.app>
