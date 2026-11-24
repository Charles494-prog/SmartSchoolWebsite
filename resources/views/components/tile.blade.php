@props(['item'])
@php
    $colors = [
        'indigo' => 'bg-indigo-50 text-indigo-600 group-hover:bg-indigo-600', 'violet' => 'bg-violet-50 text-violet-600 group-hover:bg-violet-600',
        'sky' => 'bg-sky-50 text-sky-600 group-hover:bg-sky-600', 'emerald' => 'bg-emerald-50 text-emerald-600 group-hover:bg-emerald-600',
        'amber' => 'bg-amber-50 text-amber-600 group-hover:bg-amber-500', 'rose' => 'bg-rose-50 text-rose-600 group-hover:bg-rose-600',
        'slate' => 'bg-slate-100 text-slate-600 group-hover:bg-slate-700',
    ];
@endphp
<a href="{{ $item['url'] }}" class="group card flex items-start gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-lg hover:ring-brand-200">
    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl transition group-hover:text-white {{ $colors[$item['color']] ?? $colors['indigo'] }}">
        <x-icon :name="$item['icon']" class="h-5 w-5" />
    </div>
    <div class="min-w-0">
        <div class="font-semibold text-slate-900">{{ $item['label'] }}</div>
        <div class="mt-0.5 text-sm text-slate-500">{{ $item['desc'] }}</div>
    </div>
</a>
