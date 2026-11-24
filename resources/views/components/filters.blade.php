{{-- Vercel-style filters: "Add filter" menu, one chip per active filter, "Clear all" on the right.
     $filters = ['param' => ['label' => 'Grade level', 'options' => [value => label]]] --}}
@props(['filters' => [], 'search' => null, 'keep' => []])
@php
    $active = collect($filters)->filter(fn ($f, $k) => request()->filled($k));
    $url = fn (array $set) => request()->fullUrlWithQuery(array_merge(['page' => null], $set));
@endphp
<div class="mb-5 flex flex-wrap items-center gap-2">
    @if($search)
        <form class="relative w-full sm:w-72">
            @foreach(request()->except(['q', 'page']) as $k => $v)@if(is_string($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400" />
            <input name="q" value="{{ request('q') }}" placeholder="{{ $search }}" class="input pl-9">
        </form>
    @endif

    <div x-data="{ open:false, pick:null }" class="relative">
        <button type="button" @click="open=!open; pick=null" class="inline-flex items-center gap-2 rounded-full bg-white px-3.5 py-2 text-sm font-medium text-slate-700 shadow-sm ring-1 ring-slate-200 hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-200 dark:ring-white/10">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M7 12h10M10 18h4"/></svg> Add filter
        </button>
        <div x-show="open" x-cloak x-transition @click.outside="open=false" class="absolute left-0 z-30 mt-2 w-64 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-900/10 dark:bg-slate-900 dark:ring-white/10">
            <div x-show="!pick" class="p-1">
                @foreach($filters as $k => $f)
                    <button type="button" @click="pick='{{ $k }}'" class="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-white/5">{{ $f['label'] }} <x-icon name="chevron" class="h-4 w-4 text-slate-400" /></button>
                @endforeach
            </div>
            @foreach($filters as $k => $f)
                <div x-show="pick==='{{ $k }}'" x-cloak class="max-h-72 overflow-y-auto p-1">
                    <button type="button" @click="pick=null" class="flex w-full items-center gap-1 rounded-lg px-3 py-2 text-left text-xs font-semibold text-slate-500 hover:bg-slate-50"><x-icon name="chevron-left" class="h-3.5 w-3.5" /> {{ $f['label'] }}</button>
                    @foreach($f['options'] as $v => $label)
                        <a href="{{ $url([$k => $v]) }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-slate-50 dark:hover:bg-white/5">{{ $label }} @if((string) request($k) === (string) $v)<x-icon name="check" class="h-4 w-4 text-brand-600" />@endif</a>
                    @endforeach
                </div>
            @endforeach
        </div>
    </div>

    @foreach($active as $k => $f)
        <a href="{{ $url([$k => null]) }}" class="group inline-flex items-center gap-1.5 rounded-full border border-dashed border-slate-300 px-3.5 py-1.5 text-sm hover:border-rose-300 hover:bg-rose-50 dark:border-white/20" title="Remove filter">
            <span class="text-slate-500">{{ $f['label'] }}</span>
            <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $f['options'][request($k)] ?? request($k) }}</span>
            <x-icon name="x" class="h-3.5 w-3.5 text-slate-400 group-hover:text-rose-500" />
        </a>
    @endforeach

    @if($active->isNotEmpty() || request()->filled('q'))
        <a href="{{ request()->url() }}{{ $keep ? '?'.http_build_query(request()->only($keep)) : '' }}" class="ml-auto text-sm font-medium text-slate-500 hover:text-rose-600">Clear all filters</a>
    @endif
</div>
