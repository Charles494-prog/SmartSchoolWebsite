@props(['label', 'value', 'tone' => 'slate', 'href' => null, 'icon' => null])
@php
    $tones = [
        'slate' => ['text-slate-900', 'bg-slate-100 text-slate-600'],
        'green' => ['text-emerald-700', 'bg-emerald-100 text-emerald-600'],
        'red' => ['text-rose-700', 'bg-rose-100 text-rose-600'],
        'amber' => ['text-amber-700', 'bg-amber-100 text-amber-600'],
        'brand' => ['text-brand-700', 'bg-brand-100 text-brand-600'],
    ];
    [$txt, $chip] = $tones[$tone] ?? $tones['slate'];
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if($href) href="{{ $href }}" @endif class="card group flex items-center gap-4 p-5 {{ $href ? 'transition hover:-translate-y-0.5 hover:shadow-md' : '' }}">
    @if($icon)<div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl {{ $chip }}"><x-icon :name="$icon" class="h-6 w-6" /></div>@endif
    <div class="min-w-0">
        <div class="truncate text-2xl font-bold {{ $txt }}">{{ $value }}</div>
        <div class="text-sm text-slate-500">{{ $label }}</div>
    </div>
    @if($href)<x-icon name="chevron" class="ml-auto h-4 w-4 text-slate-300 transition group-hover:text-brand-500" />@endif
</{{ $tag }}>
