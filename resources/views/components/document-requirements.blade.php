{{-- Editor for the documents families upload with an online application. Posts docs[i][key|label|required]. --}}
@php
    $rows = collect(old('docs') ?? collect(\App\Support\Admission::documents())->map(fn ($d, $k) => ['key' => $k, 'label' => $d['label'], 'required' => $d['required']])->values())
        ->map(fn ($r) => ['key' => (string) ($r['key'] ?? ''), 'label' => (string) ($r['label'] ?? ''), 'required' => filter_var($r['required'] ?? false, FILTER_VALIDATE_BOOLEAN)])->values();
@endphp
<div x-data="{ rows: @js($rows) }" class="space-y-2">
    <input type="hidden" name="docs_present" value="1">
    <template x-for="(r, i) in rows" :key="i">
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 p-2 sm:flex-nowrap">
            <input type="hidden" :name="'docs[' + i + '][key]'" :value="r.key">
            <input type="text" class="input min-h-11 flex-1" :name="'docs[' + i + '][label]'" x-model="r.label" placeholder="Document name, e.g. Birth certificate" aria-label="Document name" maxlength="80">
            <label class="flex min-h-11 cursor-pointer items-center gap-2 px-2 text-sm text-slate-700">
                <input type="hidden" :name="'docs[' + i + '][required]'" value="0">
                <input type="checkbox" class="h-4 w-4 rounded" :name="'docs[' + i + '][required]'" value="1" x-model="r.required"> Required
            </label>
            <button type="button" class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 hover:bg-rose-50 hover:text-rose-600" x-on:click="if (confirm('Remove this document from the list?')) rows.splice(i, 1)" aria-label="Remove document"><x-icon name="trash" class="h-4 w-4" /></button>
        </div>
    </template>
    <p x-show="!rows.length" x-cloak class="muted">No documents asked. Families will not see an upload section.</p>
    <button type="button" class="btn btn-sm min-h-11" x-on:click="rows.push({ key: '', label: '', required: false })" x-bind:disabled="rows.length >= 20"><x-icon name="plus" class="h-4 w-4" /> Add document</button>
    @error('docs')<p class="field-error">{{ $message }}</p>@enderror
</div>
