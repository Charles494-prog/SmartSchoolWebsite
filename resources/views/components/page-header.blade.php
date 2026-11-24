@props(['title', 'subtitle' => null, 'back' => null, 'backLabel' => 'Back'])
<div class="mb-6 sm:mb-8">
    @if($back)
        <a href="{{ $back }}" class="mb-3 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-brand-600"><x-icon name="arrow-left" class="h-4 w-4" /> {{ $backLabel }}</a>
    @endif
    <div class="flex flex-wrap items-end gap-4">
        <div class="min-w-0 flex-1">
            <h1 class="h1">{{ $title }}</h1>
            @if($subtitle)<p class="mt-1.5 text-slate-500">{{ $subtitle }}</p>@endif
        </div>
        @if(trim($slot))<div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>@endif
    </div>
</div>
