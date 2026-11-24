@props(['name', 'label', 'checked' => false, 'help' => null])
<label class="flex cursor-pointer items-center justify-between gap-4 rounded-xl p-3 ring-1 ring-slate-200 hover:bg-slate-50" x-data="{ on: @js((bool) old($name, $checked)) }">
    <span><span class="block text-sm font-medium text-slate-800">{{ $label }}</span>@if($help)<span class="block text-xs text-slate-500">{{ $help }}</span>@endif</span>
    <input type="hidden" name="{{ $name }}" :value="on ? 1 : 0">
    <button type="button" role="switch" :aria-checked="on" @click="on=!on" aria-label="{{ $label }}"
            :class="on ? 'bg-brand-600' : 'bg-slate-300'" class="relative inline-flex h-6 w-11 shrink-0 rounded-full transition">
        <span :class="on ? 'translate-x-5' : 'translate-x-0.5'" class="mt-0.5 inline-block h-5 w-5 rounded-full bg-white shadow transition"></span>
    </button>
</label>
